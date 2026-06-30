<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foreign_sponsor_configs', function (Blueprint $table) {
            $table->json('selected_program_ids')->nullable()->after('available_courses');
        });
    }

    public function down(): void
    {
        Schema::table('foreign_sponsor_configs', function (Blueprint $table) {
            $table->dropColumn('selected_program_ids');
        });
    }
};
