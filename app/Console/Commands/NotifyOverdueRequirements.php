<?php

namespace App\Console\Commands;

use App\Models\Requirement;
use App\Models\User;
use App\Notifications\OverdueRequirementReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyOverdueRequirements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'requirements:notify-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify employees who have overdue, unsubmitted post-training requirements.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $missing = DB::table('requirements as r')
            ->join('batches as b', 'r.batch_id', '=', 'b.id')
            ->join('participants as p', 'p.batch_id', '=', 'b.id')
            ->where('r.is_required', true)
            ->where('p.attendance', '!=', 'Absent')
            ->whereDate('r.due_date', '<', now()->toDateString())
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('submissions as s')
                    ->join('participants as p2', 's.participant_id', '=', 'p2.id')
                    ->whereColumn('s.requirement_id', 'r.id')
                    ->whereColumn('p2.empcode', 'p.empcode');
            })
            ->select(['p.empcode as empcode', 'r.id as requirement_id', 'r.due_date as due_date'])
            ->distinct()
            ->get();

        $notified = 0;

        foreach ($missing->groupBy('empcode') as $empcode => $rows) {
            $user = User::where('empcode', $empcode)->first();

            if (! $user) {
                continue;
            }

            $alreadyNotifiedRequirementIds = $user->notifications()
                ->where('type', OverdueRequirementReminder::class)
                ->get()
                ->pluck('data.requirement_id')
                ->all();

            foreach ($rows as $row) {
                if (in_array($row->requirement_id, $alreadyNotifiedRequirementIds)) {
                    continue;
                }

                $requirement = Requirement::find($row->requirement_id);

                if (! $requirement) {
                    continue;
                }

                $daysOverdue = now()->startOfDay()->diffInDays(Carbon::parse($row->due_date)->startOfDay());

                $user->notify(new OverdueRequirementReminder($requirement, $daysOverdue));
                $notified++;
            }
        }

        $this->info("Sent {$notified} overdue requirement notification(s).");

        return self::SUCCESS;
    }
}
