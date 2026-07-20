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
        Schema::table('programs', function (Blueprint $table) {
            $table->string('added_by')->nullable()->after('origin');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->string('added_by')->nullable()->after('hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('added_by');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn('added_by');
        });
    }
};
