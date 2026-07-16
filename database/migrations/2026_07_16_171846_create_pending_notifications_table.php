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
        Schema::create('pending_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('empcode');
            $table->string('type');
            $table->json('data');
            $table->timestamps();

            $table->index('empcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_notifications');
    }
};
