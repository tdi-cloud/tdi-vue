<?php

namespace App\Console\Commands;

use App\Models\Program;
use App\Models\Requirement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateOldRequirements extends Command
{
    /**
     * Usage: php artisan requirements:migrate-old
     */
    protected $signature = 'requirements:migrate-old';

    protected $description = 'One-time migration: ilipat ang lumang per-program requirements (old_requirements table) papunta sa bagong per-batch requirements table';

    /**
     * Mapping ng mga lumang title papunta sa bagong title.
     * Ang AAR sa lumang data ay After Activity Report sa bago.
     */
    protected array $titleMap = [
        'AAR' => 'After Activity Report',
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('old_requirements')) {
            $this->error("Walang 'old_requirements' table. I-import muna ang lumang data (tingnan ang instructions).");
            return self::FAILURE;
        }

        $oldRows = DB::table('old_requirements')->get();

        if ($oldRows->isEmpty()) {
            $this->warn('Walang laman ang old_requirements table.');
            return self::SUCCESS;
        }

        $created = 0;
        $skippedExisting = 0;
        $missingPrograms = [];
        $noBatches = [];

        foreach ($oldRows as $row) {
            // I-map ang lumang title (hal. AAR → After Activity Report)
            $title = $this->titleMap[$row->title] ?? $row->title;

            // Siguraduhing kilala ng bagong system ang title na ito
            if (! array_key_exists($title, Requirement::TYPES)) {
                $this->warn("Nilaktawan: hindi kilalang title '{$row->title}' (program {$row->program_code})");
                continue;
            }

            $program = Program::with('batches')
                ->where('program_code', $row->program_code)
                ->first();

            if (! $program) {
                $missingPrograms[] = $row->program_code;
                continue;
            }

            if ($program->batches->isEmpty()) {
                $noBatches[] = $row->program_code;
                continue;
            }

            // Isang bagong row PER BATCH ng program
            foreach ($program->batches as $batch) {
                $requirement = Requirement::updateOrCreate(
                    [
                        'batch_id' => $batch->id,
                        'title'    => $title,
                    ],
                    [
                        'name'        => Requirement::nameFor($title),
                        'due_date'    => Requirement::dueDateFor($title, $batch->date_end),
                        'is_required' => $row->required === 'on' || $row->required === '1' || $row->required === 1,
                        'note'        => $row->description,
                    ]
                );

                $requirement->wasRecentlyCreated ? $created++ : $skippedExisting++;
            }
        }

        $this->info("✅ Tapos na!");
        $this->line("   Nagawa: {$created} requirement(s)");
        $this->line("   Nilaktawan (existing na): {$skippedExisting}");

        if ($missingPrograms) {
            $this->warn('   Mga program_code na WALA sa bagong database: ' . implode(', ', array_unique($missingPrograms)));
        }

        if ($noBatches) {
            $this->warn('   Mga program na WALANG batches (walang malilikha): ' . implode(', ', array_unique($noBatches)));
        }

        $this->line('');
        $this->line("Kapag na-verify mo nang tama ang nalipat, pwede mo nang burahin ang temp table:");
        $this->line("   DROP TABLE old_requirements;");

        return self::SUCCESS;
    }
}

// php artisan requirements:migrate-old