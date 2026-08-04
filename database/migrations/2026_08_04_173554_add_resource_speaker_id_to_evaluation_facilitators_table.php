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
        Schema::table('evaluation_facilitators', function (Blueprint $table) {
            $table->foreignId('resource_speaker_id')->nullable()->after('evaluation_form_id')
                ->constrained('resource_speakers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_facilitators', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resource_speaker_id');
        });
    }
};
