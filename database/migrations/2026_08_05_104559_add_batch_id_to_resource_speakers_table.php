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
        Schema::table('resource_speakers', function (Blueprint $table) {
            // Null = general/program-wide speaker (visible to every batch).
            // Set = specific to that batch only. If the batch is deleted, the
            // speaker just becomes program-wide again rather than being lost.
            $table->foreignId('batch_id')->nullable()->after('program_id')
                ->constrained('batches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resource_speakers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
