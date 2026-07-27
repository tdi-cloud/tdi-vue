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
        Schema::create('evaluation_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_id')->constrained()->cascadeOnDelete();
            $table->string('key'); // content|methodology|facilitators|planned_actions|overall
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['evaluation_form_id', 'key']);
            $table->index(['evaluation_form_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_sections');
    }
};
