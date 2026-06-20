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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->integer('sort_order')->default(0);
            $table->string('program_code');
            $table->string('batch');
            $table->string('status');
            $table->string('modality');
            $table->text('venue')->nullable();
            $table->string('date_start');
            $table->string('date_end');
            $table->string('time_start');
            $table->string('time_end');
            $table->string('days');
            $table->string('hours');
            $table->timestamps();

            // Kapag na-delete ang program, automatic ding mabubura ang batches nito
            $table->foreign('program_code')
                ->references('program_code')
                ->on('programs')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};