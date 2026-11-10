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
        Schema::table('absent_justifications', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('file_path');
            $table->unsignedInteger('file_size')->nullable()->after('original_name'); // bytes
            $table->string('uploaded_by_empcode')->nullable()->after('file_size');
            $table->string('uploaded_by_name')->nullable()->after('uploaded_by_empcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absent_justifications', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'file_size', 'uploaded_by_empcode', 'uploaded_by_name']);
        });
    }
};
