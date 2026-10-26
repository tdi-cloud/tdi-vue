<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('terminal_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->string('report_type'); // TREAP or REAP

            // I. Program Description (prefilled from the program/batch, editable)
            $table->json('program_description');

            // II. Re-Entry Action Plan (REAP only)
            $table->text('competencies_gained')->nullable();
            $table->text('reap_summary')->nullable();
            $table->text('reap_objectives')->nullable();
            $table->json('action_plan')->nullable();

            // Signatories: { empcode, name, position, office, date }
            $table->json('prepared_by');
            $table->json('approved_by');

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'requirement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_reports');
    }
};
