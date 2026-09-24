<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\Participant;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Naghahanap ng mga participant na doble (parehong batch_id + empcode) at
 * pinipili kung sino ang mananatili — ang may totoong data (submissions,
 * certificates, justification) ang laging mananatili, hindi basta ang una o
 * huling na-encode. Ang mga submissions/certificates/justification ng
 * mabubura ay inililipat muna sa mananatiling row bago sila tanggalin, kaya
 * walang totoong record na nawawala.
 *
 * Kung may TUNAY na conflict (parehong may submission/certificate para sa
 * eksaktong parehong requirement/type), hindi ito awtomatikong lulutasin —
 * mananatiling doble ang row na iyon at ire-report na lang para sa manual
 * review, para walang mabura nang hindi sinasadya.
 *
 * Usage:
 *   php artisan participants:dedupe            (dry run — ulat lang, walang binabago)
 *   php artisan participants:dedupe --force     (totoong pagbura, naka-transaction kada grupo)
 */
class DedupeParticipants extends Command
{
    protected $signature = 'participants:dedupe {--force : Actually merge and delete instead of just reporting}';

    protected $description = 'Find duplicate participants (same batch + empcode) and safely merge/remove them.';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');

        $groups = DB::table('participants')
            ->select('batch_id', 'empcode', DB::raw('COUNT(*) as total'))
            ->groupBy('batch_id', 'empcode')
            ->having('total', '>', 1)
            ->orderBy('batch_id')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('Walang nahanap na duplicate participants. Malinis ang data.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN — walang binabago] ' : '[LIVE] ')."{$groups->count()} duplicate group(s) na nahanap.");
        $this->newLine();

        $totalDeleted = 0;
        $flagged = [];

        foreach ($groups as $group) {
            $rows = Participant::withCount(['submissions', 'certificates'])
                ->with('justification')
                ->where('batch_id', $group->batch_id)
                ->where('empcode', $group->empcode)
                ->orderBy('id')
                ->get();

            $winner = $this->pickWinner($rows);
            $losers = $rows->where('id', '!=', $winner->id)->values();

            $this->line(
                "Batch {$group->batch_id} / {$group->empcode}: mananatili #{$winner->id} ".
                "(submissions: {$winner->submissions_count}, certificates: {$winner->certificates_count}, ".
                'justification: '.($winner->justification ? 'meron' : 'wala').') — '.
                'burahin: #'.$losers->pluck('id')->implode(', #')
            );

            foreach ($losers as $loser) {
                $result = $dryRun
                    ? $this->previewMerge($winner, $loser)
                    : DB::transaction(fn () => $this->mergeAndDelete($winner, $loser));

                foreach ($result['notes'] as $note) {
                    $this->warn('  ⚠ '.$note);
                    $flagged[] = $note;
                }

                if ($result['deleted']) {
                    $this->line("  ✓ #{$loser->id} — ".($dryRun ? 'buburahin (walang laman pagkatapos i-merge)' : 'binura'));
                    $totalDeleted++;
                } else {
                    $this->warn("  ✗ #{$loser->id} — HINDI ".($dryRun ? 'ide-delete' : 'binura').' dahil may hindi malinaw na conflict, manual review muna.');
                }
            }

            $this->newLine();
        }

        $this->info($dryRun
            ? "[DRY RUN] {$totalDeleted} row ang buburahin kapag pinatakbo ulit gamit ang --force."
            : "{$totalDeleted} duplicate row ang tinanggal.");

        if ($flagged) {
            $this->newLine();
            $this->warn(count($flagged).' na item ang nangangailangan ng MANUAL REVIEW (may conflict sa data, hindi awtomatikong nilutas):');
            foreach ($flagged as $note) {
                $this->line('  - '.$note);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Ang row na may pinaka-maraming totoong data (submissions muna, tapos
     * certificates, tapos justification) ang "winner". Kapag magkatabla
     * lahat, ang pinaka-una (earliest id/created_at) ang mananatili.
     */
    private function pickWinner($rows): Participant
    {
        return $rows->sort(function (Participant $a, Participant $b) {
            if ($a->submissions_count !== $b->submissions_count) {
                return $b->submissions_count <=> $a->submissions_count;
            }
            if ($a->certificates_count !== $b->certificates_count) {
                return $b->certificates_count <=> $a->certificates_count;
            }
            $aJust = $a->justification ? 1 : 0;
            $bJust = $b->justification ? 1 : 0;
            if ($aJust !== $bJust) {
                return $bJust <=> $aJust;
            }

            return $a->id <=> $b->id;
        })->first();
    }

    /**
     * Dry-run lang — sinusuri kung anong ililipat/mag-co-conflict, pero
     * walang binabagong data. Parehong logic sa mergeAndDelete() pero
     * read-only.
     */
    private function previewMerge(Participant $winner, Participant $loser): array
    {
        $notes = [];

        $winnerReqIds = $winner->submissions()->pluck('requirement_id');
        $conflictingSubs = Submission::where('participant_id', $loser->id)
            ->whereIn('requirement_id', $winnerReqIds)
            ->count();
        if ($conflictingSubs > 0) {
            $notes[] = "Participant #{$loser->id}: {$conflictingSubs} submission(s) na may kaparehong requirement sa winner #{$winner->id} — pareho silang may sariling submission, kailangan pumili ng tao kung alin ang tama.";
        }

        $winnerCertKeys = $winner->certificates()->get(['batch_id', 'type'])
            ->map(fn ($c) => "{$c->batch_id}-{$c->type}");
        $conflictingCerts = $loser->certificates()->get(['batch_id', 'type'])
            ->filter(fn ($c) => $winnerCertKeys->contains("{$c->batch_id}-{$c->type}"))
            ->count();
        if ($conflictingCerts > 0) {
            $notes[] = "Participant #{$loser->id}: {$conflictingCerts} certificate(s) na may kaparehong batch+type sa winner #{$winner->id}.";
        }

        if ($loser->justification && $winner->justification) {
            $notes[] = "Participant #{$loser->id}: pareho silang may absence justification memo — kailangan pumili ng tao kung alin ang tama.";
        }

        $wouldDelete = $conflictingSubs === 0 && $conflictingCerts === 0 && ! ($loser->justification && $winner->justification);

        return ['deleted' => $wouldDelete, 'notes' => $notes];
    }

    /**
     * Totoong pag-merge: ilipat ang walang conflict na submissions/
     * certificates/justification papunta sa winner, tapos burahin ang loser
     * KUNG walang natirang laman (kung may conflict, hindi natin ito
     * gagalawin — mananatiling doble muna hanggang malutas ng tao).
     */
    private function mergeAndDelete(Participant $winner, Participant $loser): array
    {
        $notes = [];

        $winnerReqIds = $winner->submissions()->pluck('requirement_id');
        Submission::where('participant_id', $loser->id)
            ->whereNotIn('requirement_id', $winnerReqIds)
            ->update(['participant_id' => $winner->id]);

        $remainingSubs = Submission::where('participant_id', $loser->id)->count();
        if ($remainingSubs > 0) {
            $notes[] = "Participant #{$loser->id}: {$remainingSubs} submission(s) na may kaparehong requirement sa winner #{$winner->id} — HINDI inilipat, manual review.";
        }

        $winnerCertKeys = $winner->certificates()->get(['batch_id', 'type'])
            ->map(fn ($c) => "{$c->batch_id}-{$c->type}");
        $loser->certificates()->get()
            ->each(function (Certificate $cert) use ($winner, $winnerCertKeys) {
                if (! $winnerCertKeys->contains("{$cert->batch_id}-{$cert->type}")) {
                    $cert->update(['participant_id' => $winner->id]);
                }
            });

        $remainingCerts = $loser->certificates()->count();
        if ($remainingCerts > 0) {
            $notes[] = "Participant #{$loser->id}: {$remainingCerts} certificate(s) na may kaparehong batch+type sa winner #{$winner->id} — HINDI inilipat, manual review.";
        }

        if ($loser->justification && ! $winner->justification) {
            $loser->justification->update(['participant_id' => $winner->id]);
        } elseif ($loser->justification && $winner->justification) {
            $notes[] = "Participant #{$loser->id}: may sariling absence justification na hindi na-merge (meron na rin ang winner) — manual review.";
        }

        $canDelete = $remainingSubs === 0
            && $remainingCerts === 0
            && ! $loser->fresh()->justification;

        if ($canDelete) {
            $loser->delete();
        }

        return ['deleted' => $canDelete, 'notes' => $notes];
    }
}
