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
        Schema::create('regional_reports', function (Blueprint $table) {
            $table->id();
            $table->string('region');
            $table->string('month');
            $table->integer('year');                          
            $table->string('file_name');
            $table->string('file_path');
            $table->timestamp('submitted_at')->nullable();    
            $table->text('notes')->nullable();                
            $table->string('added_by');
            $table->timestamps();   
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regional_reports');
    }
};
