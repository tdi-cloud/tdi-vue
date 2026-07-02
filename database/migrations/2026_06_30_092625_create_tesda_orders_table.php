<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tesda_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();

            // Header
            $table->string('subject');
            $table->date('date_issued')->nullable();
            $table->string('effectivity')->default('As indicated');
            $table->string('supersedes')->nullable();
            $table->unsignedInteger('series_year');
            $table->unsignedInteger('total_pages')->default(1);

            // Body (rich text HTML, editable)
            $table->longText('body');

            // Participants table options
            $table->boolean('include_participants')->default(false);
            $table->boolean('include_batch_data')->default(false);

            // Closure (rich text HTML, editable)
            $table->longText('closure');

            // Signatory
            $table->string('signatory_empcode')->nullable();
            $table->string('signatory_name');
            $table->string('signatory_position');

            // Generated file
            $table->string('pdf_path')->nullable();
            $table->string('generated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tesda_orders');
    }
};