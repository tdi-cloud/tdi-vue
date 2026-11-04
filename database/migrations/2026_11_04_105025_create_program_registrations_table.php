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
        Schema::create('program_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->string('source');

            // Snapshot of the employee record at the time of registration.
            $table->string('empcode');
            $table->string('lastname');
            $table->string('firstname');
            $table->string('mi')->nullable();
            $table->string('office_division')->nullable();
            $table->string('position')->nullable();
            $table->string('sg')->nullable();
            $table->string('plantilla_status')->nullable();
            $table->string('sex')->nullable();
            $table->string('region')->nullable();
            $table->string('office')->nullable();
            $table->string('location')->nullable();
            $table->string('section')->nullable();
            $table->string('unit')->nullable();

            $table->string('email');
            $table->string('contact_number');
            $table->json('custom_answers')->nullable();
            $table->timestamp('data_privacy_consent_at')->nullable();

            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'empcode']);
            $table->index(['registration_form_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_registrations');
    }
};
