<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_sponsor_configs', function (Blueprint $table) {
            $table->id();
            $table->string('organizing_sponsor');
            $table->string('slug')->unique();
            $table->string('form_title');
            $table->boolean('is_active')->default(true);
            $table->json('available_courses')->nullable(); // [{title, url}]
            $table->text('accomplished_form_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreign_sponsor_configs');
    }
};