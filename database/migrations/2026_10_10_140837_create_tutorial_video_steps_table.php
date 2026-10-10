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
        Schema::create('tutorial_video_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutorial_video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('image_path')->nullable(); // screenshot for this step
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutorial_video_steps');
    }
};
