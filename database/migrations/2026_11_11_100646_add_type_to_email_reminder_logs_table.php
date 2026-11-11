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
        Schema::table('email_reminder_logs', function (Blueprint $table) {
            $table->string('type', 20)->default('reminder')->after('id');

            $table->index(['batch_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_reminder_logs', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'type']);
            $table->dropColumn('type');
        });
    }
};
