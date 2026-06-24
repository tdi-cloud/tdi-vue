<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foreign_sponsor_configs', function (Blueprint $table) {
            $table->json('selected_program_ids')->nullable()->after('accomplished_form_note');
        });
    }

    public function down(): void
    {
        Schema::table('foreign_sponsor_configs', function (Blueprint $table) {
            $table->dropColumn('selected_program_ids');
        });
    }
};