<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('program_code');
            $table->string('empcode'); 
            $table->string('certificate_number')->unique()->nullable();
            $table->string('type')->default('Participation');
            // Participation, Completion, Appearance, Appreciation, Recognition, Achievement
            $table->date('issued_date')->nullable();
            $table->decimal('hours', 6, 1)->default(0);
            $table->string('status')->default('Pending');
            // Pending, Issued, Revoked
            $table->text('file_path')->nullable();
            $table->string('file_name')->nullable(); 
            $table->string('uploaded_by')->nullable(); 
            $table->string('issued_by')->nullable();   
            $table->text('remarks')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();
            $table->unique(['participant_id', 'batch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};