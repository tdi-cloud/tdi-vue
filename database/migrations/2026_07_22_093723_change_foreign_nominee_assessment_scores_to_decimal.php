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
        // Requirements scores now accept decimal values (e.g. 18.5) instead
        // of whole numbers only — the client needs fractional grading.
        Schema::table('foreign_nominee_assessments', function (Blueprint $table) {
            $table->decimal('need_for_training', 5, 2)->nullable()->change();
            $table->decimal('relevance_to_duties', 5, 2)->nullable()->change();
            $table->decimal('meets_donor_requirements', 5, 2)->nullable()->change();
            $table->decimal('completion_of_documents', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foreign_nominee_assessments', function (Blueprint $table) {
            $table->unsignedTinyInteger('need_for_training')->nullable()->change();
            $table->unsignedTinyInteger('relevance_to_duties')->nullable()->change();
            $table->unsignedTinyInteger('meets_donor_requirements')->nullable()->change();
            $table->unsignedTinyInteger('completion_of_documents')->nullable()->change();
        });
    }
};
