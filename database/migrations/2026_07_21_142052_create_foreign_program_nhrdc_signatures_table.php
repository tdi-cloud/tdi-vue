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
        Schema::create('foreign_program_nhrdc_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foreign_program_id')->constrained('foreign_programs')->cascadeOnDelete();
            $table->string('nhrdc_empcode');
            $table->string('signed_copy_path')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['foreign_program_id', 'nhrdc_empcode'], 'fpns_program_nhrdc_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foreign_program_nhrdc_signatures');
    }
};
