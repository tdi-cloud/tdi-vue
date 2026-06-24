<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_nominees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foreign_program_id')
                  ->constrained('foreign_programs')
                  ->cascadeOnDelete();
            $table->foreignId('foreign_sponsor_config_id')
                  ->constrained('foreign_sponsor_configs')
                  ->cascadeOnDelete();
            $table->string('firstname');
            $table->string('middle_name')->nullable();
            $table->string('surname');
            $table->enum('sex', ['male', 'female', 'other']);
            $table->integer('age');
            $table->string('position');
            $table->string('agency');
            $table->string('contact_number')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', [
                'for_interview',
                'endorsed',
                'waiting_result',
                'not_endorsed',
                'accepted',
                'regret',
                'cancelled',
            ])->default('for_interview');
            $table->string('accomplished_form_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreign_nominees');
    }
};