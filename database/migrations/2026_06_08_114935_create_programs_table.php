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
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->integer('sort_order')->default(0);
            $table->string('program_code')->unique()->nullable();
            $table->text('title');
            $table->text('description')->nullable();
            $table->string('competency')->nullable();
            $table->string('modality');
            $table->string('pax');
            $table->string('category');
            $table->string('type');
            $table->string('initiated');
            $table->string('provider')->nullable();
            $table->string('cost');
            $table->string('fund');
            $table->string('origin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
