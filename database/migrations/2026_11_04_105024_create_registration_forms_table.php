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
        Schema::create('registration_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title')->default('Program Registration');
            $table->text('intro_text')->nullable();
            $table->string('background_image')->nullable();
            $table->string('theme')->default('blue');
            $table->boolean('is_open')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->unsignedInteger('slot_limit')->nullable();
            $table->string('created_by_empcode')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_forms');
    }
};
