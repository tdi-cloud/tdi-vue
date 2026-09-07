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
        Schema::create('program_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('program_code')->nullable(); // snapshot — hindi FK dahil puwedeng mabura na ang program (hard delete)
            $table->string('title');
            $table->string('action'); // created | updated | deleted
            $table->string('performed_by')->nullable();
            $table->json('meta')->nullable(); // hal. { "changed_fields": [...] } para sa "updated"
            $table->timestamps();

            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_activity_logs');
    }
};
