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
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->integer('sort_order')->default(0);
            $table->foreignId('batch_id')->constrained('batches')->onDelete('cascade');
            $table->string('empcode');
            $table->string('attendance');
            $table->decimal('hours', 6, 1)->default(0);
            $table->string('requirements')->nullable();
            $table->string('added_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
