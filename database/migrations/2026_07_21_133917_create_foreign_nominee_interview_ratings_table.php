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
        Schema::create('foreign_nominee_interview_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foreign_nominee_id')->constrained('foreign_nominees')->cascadeOnDelete();

            // Rater identity, snapshotted at rating time (same pattern as
            // TnaAssessment's supervisor fields) so it stays stable even if the
            // employee's record changes later.
            $table->string('nhrdc_empcode');
            $table->string('nhrdc_name')->nullable();
            $table->string('nhrdc_position')->nullable();

            // Interview criteria (max 30 total)
            $table->unsignedTinyInteger('communication_skills')->default(0);
            $table->unsignedTinyInteger('alertness')->default(0);
            $table->unsignedTinyInteger('judgement')->default(0);
            $table->unsignedTinyInteger('self_confidence')->default(0);
            $table->unsignedTinyInteger('emotional_stability')->default(0);
            $table->unsignedTinyInteger('appearance')->default(0);

            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();

            // One rating per NHRDC member per nominee — re-saving updates it.
            $table->unique(['foreign_nominee_id', 'nhrdc_empcode'], 'fnir_nominee_nhrdc_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foreign_nominee_interview_ratings');
    }
};
