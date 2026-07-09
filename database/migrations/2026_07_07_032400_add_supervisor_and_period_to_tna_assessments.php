<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dagdag: napiling supervisor (snapshot mula sa employees) at TNA period.
     * Hiwalay na migration para hindi mo na kailangang i-reset ang DB kung
     * na-migrate mo na ang orihinal na tna_assessments.
     */
    public function up(): void
    {
        Schema::table('tna_assessments', function (Blueprint $table) {
            $table->string('period')->nullable()->after('position');
            $table->string('supervisor_empcode')->nullable()->after('designation');
            $table->string('supervisor_name')->nullable()->after('supervisor_empcode');
            $table->string('supervisor_position')->nullable()->after('supervisor_name');

            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::table('tna_assessments', function (Blueprint $table) {
            $table->dropIndex(['period']);
            $table->dropColumn([
                'period',
                'supervisor_empcode',
                'supervisor_name',
                'supervisor_position',
            ]);
        });
    }
};