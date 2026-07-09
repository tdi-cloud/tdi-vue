<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kailan ni-rate ng supervisor ang self-rating na ito.
     * Habang NULL ito, pwede pa itong burahin/palitan ng empleyado.
     */
    public function up(): void
    {
        Schema::table('tna_assessments', function (Blueprint $table) {
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('tna_assessments', function (Blueprint $table) {
            $table->dropColumn('supervisor_reviewed_at');
        });
    }
};