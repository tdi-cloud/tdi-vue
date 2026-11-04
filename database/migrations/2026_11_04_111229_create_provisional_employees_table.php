<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Holding list of employees added from approved registrations. The
     * official employees table is replaced every month, so this is the
     * master copy used to restore their row until HR's list includes them.
     */
    public function up(): void
    {
        Schema::create('provisional_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_registration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('empcode')->index();
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
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();

            // Identifies the row the app inserted into `employees`: ids restart
            // after the monthly re-import, so the id alone isn't enough.
            $table->unsignedBigInteger('employee_row_id')->nullable();
            $table->timestamp('employee_row_created_at')->nullable();

            $table->string('status')->default('waiting');
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provisional_employees');
    }
};
