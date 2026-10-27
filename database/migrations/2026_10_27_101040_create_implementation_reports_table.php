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
        Schema::create('implementation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_plan_activity_id')->unique()->constrained('action_plan_activities')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->text('accomplishment');
            $table->text('actual_output');
            $table->date('date_completed');
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->string('file_path');
            $table->string('status')->default('Pending'); // Pending, Approved, Rejected
            $table->text('remarks')->nullable(); // reviewer's note
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('implementation_reports');
    }
};
