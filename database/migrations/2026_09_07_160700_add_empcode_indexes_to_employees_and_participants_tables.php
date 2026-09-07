<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `employees.EMPCODE` at `participants.empcode` ay ginagamit sa join/where
 * condition ng halos LAHAT ng "requirements/compliance/dashboard"-type na
 * queries sa app na ito (Requirements Tracker, Dashboard cards, Employees
 * Map, atbp.) — pero WALANG index ang dalawang column na ito, kaya
 * full table scan ang nangyayari kada join. Ito ang pinaka-malaking
 * bottleneck ng Requirements Tracker page (at ng mga katulad na page).
 *
 * Plain (hindi unique) na index lang — hindi unique dahil posibleng may
 * mga blangko/duplicate na EMPCODE sa totoong data (hindi namin gustong
 * mag-fail ang migration na ito dahil lang dyan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('EMPCODE');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->index('empcode');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['EMPCODE']);
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex(['empcode']);
        });
    }
};
