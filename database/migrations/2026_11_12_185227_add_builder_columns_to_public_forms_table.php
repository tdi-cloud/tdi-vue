<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public forms are now built by admins (sections and questions in the
     * database). Each form keeps the start of its reference numbers (e.g.
     * "LDS" for "LDS-000042") and who created it.
     */
    public function up(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            $table->string('reference_prefix', 10)->nullable()->after('description');
            $table->foreignId('created_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('reference_prefix');
        });
    }
};
