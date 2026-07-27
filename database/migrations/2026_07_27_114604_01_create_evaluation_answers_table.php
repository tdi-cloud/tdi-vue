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
        Schema::create('evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_question_id')->constrained()->cascadeOnDelete();
            // Null for non-facilitator-section questions; set to the specific
            // facilitator for facilitator-section questions (asked once per
            // facilitator in the rendered form).
            $table->foreignId('evaluation_facilitator_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('value_numeric')->nullable(); // likert5: 1-5, scale10: 1-10
            $table->text('value_text')->nullable(); // text answers, or JSON array for checkbox
            $table->timestamps();

            $table->index('evaluation_response_id');
            $table->index('evaluation_question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_answers');
    }
};
