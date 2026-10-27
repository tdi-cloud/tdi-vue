<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each row of a REAP "Details of the Action Plan" becomes its own record so
     * it can be monitored (schedule, status, implementation report). Existing
     * rows stored as JSON on terminal_reports are moved over, then the JSON
     * column is dropped.
     */
    public function up(): void
    {
        Schema::create('action_plan_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_report_id')->constrained('terminal_reports')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('activity');
            $table->text('expected_output');
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable(); // null = no budget
            $table->text('remarks')->nullable();
            $table->boolean('added_after_submission')->default(false); // added later from the action plan tracker
            $table->timestamps();

            $table->index(['target_date']);
        });

        DB::table('terminal_reports')->whereNotNull('action_plan')->orderBy('id')->each(function ($report) {
            foreach (json_decode($report->action_plan, true) ?: [] as $index => $row) {
                $schedule = trim((string) ($row['schedule'] ?? ''));
                $remarks = trim((string) ($row['remarks'] ?? ''));

                DB::table('action_plan_activities')->insert([
                    'terminal_report_id' => $report->id,
                    'participant_id' => $report->participant_id,
                    'batch_id' => $report->batch_id,
                    'sort_order' => $index,
                    'activity' => $row['activity'] ?? '',
                    'expected_output' => $row['expected_output'] ?? '',
                    'budget' => is_numeric($row['budget'] ?? null) ? $row['budget'] : null,
                    // The old free-text schedule cannot become a date, so keep it visible in the remarks.
                    'remarks' => collect([$schedule !== '' ? "Schedule: {$schedule}" : null, $remarks ?: null])->filter()->implode(' — ') ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('terminal_reports', function (Blueprint $table) {
            $table->dropColumn('action_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terminal_reports', function (Blueprint $table) {
            $table->json('action_plan')->nullable()->after('reap_objectives');
        });

        Schema::dropIfExists('action_plan_activities');
    }
};
