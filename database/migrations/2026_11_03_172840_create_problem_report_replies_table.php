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
        Schema::create('problem_report_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_report_id')->constrained()->cascadeOnDelete();
            // Nullable so a reply stays in the conversation when its author's account is deleted.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('problem_report_replies');
    }
};
