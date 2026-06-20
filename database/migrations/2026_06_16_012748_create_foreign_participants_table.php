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
        Schema::create('foreign_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foreign_program_id')
                  ->constrained('foreign_programs')
                  ->cascadeOnDelete();
            $table->string('name');
            $table->enum('sex', ['male', 'female', 'other']);
            $table->string('position');
            $table->string('agency');
            $table->string('contact_no')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', [
                'endorsed',
                'waiting_result',
                'not_endorsed',
                'accepted',
                'regret',
                'cancelled',
            ])->default('waiting_result');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foreign_participants');
    }
};
