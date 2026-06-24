<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_nominee_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foreign_nominee_id')
                  ->constrained('foreign_nominees')
                  ->cascadeOnDelete();
            // Manually specify shorter constraint name to avoid MySQL 64-char limit
            $table->unsignedBigInteger('foreign_nominee_requirement_id');
            $table->foreign('foreign_nominee_requirement_id', 'fns_requirement_fk')
                  ->references('id')
                  ->on('foreign_nominee_requirements')
                  ->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreign_nominee_submissions');
    }
};