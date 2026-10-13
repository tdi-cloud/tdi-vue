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
        Schema::table('foreign_nominee_interview_ratings', function (Blueprint $table) {
            // Who actually entered the rating, snapshotted at rating time. The
            // rating itself always belongs to the NHRDC member (nhrdc_empcode),
            // but it may have been entered by their representative or the FSTP unit.
            $table->string('rated_by_name')->nullable()->after('rated_by');
            $table->string('rated_by_role')->nullable()->after('rated_by_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foreign_nominee_interview_ratings', function (Blueprint $table) {
            $table->dropColumn(['rated_by_name', 'rated_by_role']);
        });
    }
};
