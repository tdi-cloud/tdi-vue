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
        Schema::create('program_supporting_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->string('program_code')->nullable();
            $table->string('document_type');
            $table->string('subject');
            $table->year('document_series');
            $table->string('origin')->nullable();
            $table->string('document_number');
            $table->date('date_issued')->nullable();
            $table->string('link', 2048)->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_supporting_documents');
    }
};
