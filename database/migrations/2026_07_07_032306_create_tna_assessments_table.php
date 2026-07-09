<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header ng isang self-rating submission ng employee.
     */
    public function up(): void
    {
        Schema::create('tna_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // snapshot ng info sa oras ng pagsagot
            $table->string('position');
            $table->string('name');
            $table->string('office')->nullable();
            $table->string('division')->nullable();
            $table->string('designation')->nullable();

            $table->string('signature');                 // pinangalanang pirma (typed)
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tna_assessments');
    }
};