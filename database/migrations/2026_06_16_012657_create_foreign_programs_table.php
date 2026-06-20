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
        Schema::create('foreign_programs', function (Blueprint $table) {
            $table->id();
            $table->string('program_title');
            $table->text('description')->nullable();        
            $table->date('program_start');
            $table->date('program_end');
            $table->integer('slots');
            $table->enum('modality', ['in-person', 'online', 'hybrid']);

            // Hybrid / Online dates
            $table->date('online_start')->nullable();
            $table->date('online_end')->nullable();
            $table->date('inperson_start')->nullable();
            $table->date('inperson_end')->nullable();

            $table->string('program_cost')->nullable();     
            $table->enum('fund_source', [                    
                'SDP',
                'Other Office',
                'Sponsoring Organization',
            ])->nullable();
            $table->enum('category', ['Foreign', 'Bilateral']); 

            $table->string('organizing_sponsor');
            $table->enum('status', [
                'for_dissemination',
                'waiting_for_nominees',
                'for_interview',
                'for_endorsement',
                'no_nominee',
                'waiting_for_result',
                'ongoing',
                'concluded',
            ])->default('for_dissemination');
            $table->date('submission_date')->nullable();
            $table->date('embassy_deadline')->nullable();
            $table->date('interview_date')->nullable();
            $table->text('invited_agencies')->nullable();
            $table->string('attached_agency')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foreign_programs');
    }
};
