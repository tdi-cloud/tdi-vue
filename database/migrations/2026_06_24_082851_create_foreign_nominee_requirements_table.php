<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_nominee_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foreign_sponsor_config_id')
                  ->constrained('foreign_sponsor_configs')
                  ->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('question');
            $table->text('description')->nullable();
            $table->string('link')->nullable();
            $table->boolean('file_required')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreign_nominee_requirements');
    }
};