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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('EMPCODE');
            $table->string('OFFICE/DIVISION');
            $table->string('LASTNAME');
            $table->string('FIRSTNAME');
            $table->string('MI');
            $table->string('POSITION');
            $table->string('SG');
            $table->string('PLANTILLA STATUS');
            $table->string('SEX');
            $table->string('REGION');
            $table->string('OFFICE');
            $table->string('LOCATION');
            $table->string('SECTION');
            $table->string('UNIT');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
