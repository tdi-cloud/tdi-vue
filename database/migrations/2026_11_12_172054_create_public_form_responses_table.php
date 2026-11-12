<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Answers submitted to the public forms, one per employee per form.
     *
     * Linked to the employee by EMPCODE (never employees.id), and the
     * employee's details are copied in, because HR empties and re-imports
     * the employees table every month.
     */
    public function up(): void
    {
        Schema::create('public_form_responses', function (Blueprint $table) {
            $table->id();
            $table->string('form_slug');
            $table->string('empcode');
            $table->string('full_name');
            $table->string('email');
            $table->string('region')->nullable();
            $table->string('office_division')->nullable();
            $table->json('employee');
            $table->json('answers');
            $table->json('other_answers')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['form_slug', 'empcode']);
            $table->index(['form_slug', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_form_responses');
    }
};
