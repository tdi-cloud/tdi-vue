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
        Schema::table('registration_otps', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('form_data');
            $table->timestamp('last_sent_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_otps', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_sent_at']);
        });
    }
};
