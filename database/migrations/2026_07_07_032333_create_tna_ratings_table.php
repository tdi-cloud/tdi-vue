<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isang row kada element na sinagutan.
     * criticality: 1-3, competence: 0-4, frequency: 1-3
     */
    public function up(): void
    {
        Schema::create('tna_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tna_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('criticality')->nullable(); // 1-3
            $table->unsignedTinyInteger('competence')->nullable();  // 0-4
            $table->unsignedTinyInteger('frequency')->nullable();   // 1-3
            $table->timestamps();

            $table->index('tna_assessment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tna_ratings');
    }
};