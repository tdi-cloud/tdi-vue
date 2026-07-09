<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ito ang "TNA Tool" ng bawat position.
     * Kapag may laman para sa isang position dito, ibig sabihin
     * may TNA Tool ang employee at pwede siyang mag-self rating.
     *
     * type = 'core'      -> lahat kailangan sagutan
     * type = 'elective'  -> optional, sasagutan lang kung applicable
     */
    public function up(): void
    {
        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->string('position');                  // tugma sa employees.POSITION
            $table->string('unit');                       // pang-group ng questions
            $table->text('element');                      // ang aktwal na irerate
            $table->enum('type', ['core', 'elective'])->default('core');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('position');
            $table->index(['position', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencies');
    }
};