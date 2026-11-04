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
        Schema::table('registration_questions', function (Blueprint $table) {
            // Adds an "Other" choice where people type their own answer (single choice and checkboxes).
            $table->boolean('allow_other')->default(false)->after('options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_questions', function (Blueprint $table) {
            $table->dropColumn('allow_other');
        });
    }
};
