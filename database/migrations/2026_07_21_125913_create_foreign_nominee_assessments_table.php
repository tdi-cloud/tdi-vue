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
        Schema::create('foreign_nominee_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foreign_nominee_id')->unique()->constrained('foreign_nominees')->cascadeOnDelete();

            // Requirements assessment (max 70) — encoded once by the admin/user.
            // The Interview section (max 30) is rated separately, per NHRDC panel
            // member, in foreign_nominee_interview_ratings.
            $table->unsignedTinyInteger('need_for_training')->nullable();
            $table->unsignedTinyInteger('relevance_to_duties')->nullable();
            $table->unsignedTinyInteger('meets_donor_requirements')->nullable();
            $table->unsignedTinyInteger('completion_of_documents')->nullable();

            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foreign_nominee_assessments');
    }
};
