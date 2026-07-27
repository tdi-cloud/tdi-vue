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
        Schema::table('foreign_programs', function (Blueprint $table) {
            // Snapshotted at creation time (same pattern as
            // foreign_nominee_interview_ratings.nhrdc_empcode/nhrdc_name) so
            // it stays accurate for auditing even if the employee's record
            // changes later.
            $table->string('created_by_empcode')->nullable()->after('attached_agency');
            $table->string('created_by_name')->nullable()->after('created_by_empcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foreign_programs', function (Blueprint $table) {
            $table->dropColumn(['created_by_empcode', 'created_by_name']);
        });
    }
};
