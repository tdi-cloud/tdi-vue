<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('added_by')->nullable()->after('reviewed_by'); // empcode ng unang nag-encode/nag-upload
        });

        // I-backfill mula sa activity log ang mga submission na naka-record na
        // ang "encoded" na aksyon (pangalan lang ang naka-log, kaya hinahanap
        // ang empcode sa users — kapag iisa lang ang tugma). Ang mas luma pa
        // doon ay mananatiling null dahil wala nang paraan para malaman.
        DB::table('submission_activity_logs')
            ->where('action', 'encoded')
            ->whereNotNull('submission_id')
            ->whereNotNull('performed_by')
            ->orderBy('id')
            ->each(function ($log) {
                $empcodes = DB::table('users')->where('name', $log->performed_by)->pluck('empcode');

                if ($empcodes->count() !== 1) {
                    return;
                }

                DB::table('submissions')
                    ->where('id', $log->submission_id)
                    ->whereNull('added_by')
                    ->update(['added_by' => $empcodes->first()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('added_by');
        });
    }
};
