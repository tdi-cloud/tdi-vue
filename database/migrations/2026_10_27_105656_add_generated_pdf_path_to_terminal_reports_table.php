<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The generated PDF is an unsigned draft kept on the report; only the
     * signed copy the participant uploads becomes the requirement submission.
     */
    public function up(): void
    {
        Schema::table('terminal_reports', function (Blueprint $table) {
            $table->string('generated_pdf_path')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terminal_reports', function (Blueprint $table) {
            $table->dropColumn('generated_pdf_path');
        });
    }
};
