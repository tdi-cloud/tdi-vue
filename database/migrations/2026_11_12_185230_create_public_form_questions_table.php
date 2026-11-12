<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Questions of a public form. Answers are saved under `answer_key` and
     * the choices' `value`s, which never change once created, so wording can
     * be edited without breaking answers already sent. Removed questions are
     * soft-deleted so their answers still show in the responses and the CSV.
     */
    public function up(): void
    {
        Schema::create('public_form_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_form_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('answer_key', 60);
            $table->string('type', 20);
            $table->text('question');
            $table->text('question_fil')->nullable();
            $table->string('instruction')->nullable();
            $table->string('instruction_fil')->nullable();
            $table->json('options')->nullable();
            $table->boolean('allow_other')->default(false);
            $table->string('other_label', 100)->nullable();
            $table->string('other_label_fil', 100)->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedTinyInteger('rank_count')->nullable();
            $table->unsignedTinyInteger('max_choices')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['public_form_id', 'answer_key']);
            $table->index(['public_form_section_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_form_questions');
    }
};
