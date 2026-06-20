<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_speakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->string('program_code')->nullable();
            $table->string('name');
            $table->string('designation')->nullable();   // e.g. "Training Officer III"
            $table->string('affiliation')->nullable();    // e.g. "TESDA Region IV-A"
            $table->string('topic')->nullable();          // Topic/Subject discussed
            $table->string('expertise')->nullable();      // Field of expertise
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->date('date_engaged')->nullable();     // Date of engagement/session
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_speakers');
    }
};