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
        Schema::table('nhrdc_members', function (Blueprint $table) {
            // Standing representative assigned by the FSTP unit — authorized to
            // rate nominee interviews on this NHRDC member's behalf (all programs).
            // Unique: one person can represent only one NHRDC member.
            $table->string('representative_empcode')->nullable()->unique()->after('empcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nhrdc_members', function (Blueprint $table) {
            $table->dropUnique(['representative_empcode']);
            $table->dropColumn('representative_empcode');
        });
    }
};
