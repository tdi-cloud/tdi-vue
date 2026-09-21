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
        Schema::create('submission_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id')->nullable(); // snapshot — hindi FK dahil puwedeng mabura na ang submission (hard delete)
            $table->string('participant_name')->nullable();
            $table->string('requirement_name')->nullable();
            $table->string('program_code')->nullable();
            $table->string('batch_label')->nullable();
            $table->string('action'); // encoded | updated | reviewed | deleted
            $table->string('status')->nullable(); // snapshot ng submission status noong oras ng aksyon
            $table->string('performed_by')->nullable();
            $table->json('meta')->nullable(); // hal. { "changed_fields": [...] } o { "remarks": "..." }
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['performed_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_activity_logs');
    }
};
