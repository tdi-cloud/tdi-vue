<?php

namespace App\Console\Commands;

use App\Support\ProvisionalEmployeeSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncProvisionalEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:sync-provisional';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check employees added from registrations against the official employees list: take off the ones HR has added and restore rows the monthly re-import removed';

    /**
     * Execute the console command.
     */
    public function handle(ProvisionalEmployeeSync $sync): int
    {
        $summary = $sync->run();

        $this->info(sprintf(
            'Merged into the official list: %d. Restored after the re-import: %d. Still waiting: %d.',
            $summary[ProvisionalEmployeeSync::MERGED],
            $summary[ProvisionalEmployeeSync::RESTORED],
            $summary[ProvisionalEmployeeSync::WAITING],
        ));

        Log::info('Provisional employees checked against the official employees list', $summary);

        return self::SUCCESS;
    }
}
