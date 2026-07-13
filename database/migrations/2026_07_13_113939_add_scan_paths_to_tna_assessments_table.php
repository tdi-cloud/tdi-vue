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
        Schema::table('tna_assessments', function (Blueprint $table) {
            $table->string('self_rating_scan_path')->nullable();
            $table->string('supervisor_rating_scan_path')->nullable();
            $table->string('result_scan_subordinate_path')->nullable();
            $table->string('result_scan_supervisor_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tna_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'self_rating_scan_path',
                'supervisor_rating_scan_path',
                'result_scan_subordinate_path',
                'result_scan_supervisor_path',
            ]);
        });
    }
};
