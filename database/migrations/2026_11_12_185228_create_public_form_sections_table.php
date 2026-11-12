<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sections of a public form. Each one is a step of the form; a section
     * can be limited to employees with a minimum salary grade (e.g. the
     * LinkedIn Learning questions for SG 18 and above).
     */
    public function up(): void
    {
        Schema::create('public_form_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_form_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('step_label')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('min_salary_grade')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['public_form_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_form_sections');
    }
};
