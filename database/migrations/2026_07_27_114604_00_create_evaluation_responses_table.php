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
        Schema::create('evaluation_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('empcode')->nullable();
            $table->string('email');
            $table->string('respondent_name');
            $table->string('name_source')->default('participant'); // participant|manual
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            // NULLs are treated as distinct by MySQL's unique index, so this only
            // actually blocks duplicates when empcode is provided — matching the
            // "soft-block by empcode when given" requirement with no extra logic.
            $table->unique(['evaluation_form_id', 'empcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_responses');
    }
};
