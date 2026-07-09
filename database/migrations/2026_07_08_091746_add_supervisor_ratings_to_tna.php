<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rating ng supervisor kada element (katabi ng self-rating)
        Schema::table('tna_ratings', function (Blueprint $table) {
            $table->unsignedTinyInteger('sup_criticality')->nullable()->after('frequency'); // 1-3
            $table->unsignedTinyInteger('sup_competence')->nullable()->after('sup_criticality'); // 0-4
            $table->unsignedTinyInteger('sup_frequency')->nullable()->after('sup_competence'); // 1-3
        });

        // Identity fields na sinagot ng supervisor (na-edit man o hindi)
        Schema::table('tna_assessments', function (Blueprint $table) {
            $table->json('supervisor_form')->nullable()->after('supervisor_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tna_ratings', function (Blueprint $table) {
            $table->dropColumn(['sup_criticality', 'sup_competence', 'sup_frequency']);
        });
        Schema::table('tna_assessments', function (Blueprint $table) {
            $table->dropColumn('supervisor_form');
        });
    }
};