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
        Schema::create('email_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sent_by')->nullable();
            $table->string('sent_by_name')->nullable();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->json('recipients');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();

            $table->index(['batch_id', 'requirement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_reminder_logs');
    }
};
