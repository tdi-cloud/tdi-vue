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
        // Interview scores now accept decimal values (e.g. 4.5) instead of
        // whole numbers only — the client needs fractional grading.
        Schema::table('foreign_nominee_interview_ratings', function (Blueprint $table) {
            $table->decimal('communication_skills', 5, 2)->default(0)->change();
            $table->decimal('alertness', 5, 2)->default(0)->change();
            $table->decimal('judgement', 5, 2)->default(0)->change();
            $table->decimal('self_confidence', 5, 2)->default(0)->change();
            $table->decimal('emotional_stability', 5, 2)->default(0)->change();
            $table->decimal('appearance', 5, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foreign_nominee_interview_ratings', function (Blueprint $table) {
            $table->unsignedTinyInteger('communication_skills')->default(0)->change();
            $table->unsignedTinyInteger('alertness')->default(0)->change();
            $table->unsignedTinyInteger('judgement')->default(0)->change();
            $table->unsignedTinyInteger('self_confidence')->default(0)->change();
            $table->unsignedTinyInteger('emotional_stability')->default(0)->change();
            $table->unsignedTinyInteger('appearance')->default(0)->change();
        });
    }
};
