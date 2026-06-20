<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('title');            // TREAP, REAP, TDOR, Feedback Report, etc.
            $table->string('name');             // Buong pangalan: Terminal Report, etc.
            $table->date('due_date');           // Auto-computed mula sa batch date_end
            $table->boolean('is_required')->default(true); // Default: required
            $table->text('note')->nullable();   // Note para sa participants
            $table->timestamps();
            // Iwas duplicate: isang title lang per batch
            $table->unique(['batch_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};