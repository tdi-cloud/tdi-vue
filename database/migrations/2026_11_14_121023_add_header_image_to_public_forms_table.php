<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A wide picture an admin can upload for each public form, shown as its
     * own banner above the title and description. Separate from the
     * background picture; null means the form has no header picture.
     */
    public function up(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            $table->string('header_image')->nullable()->after('hide_background');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            $table->dropColumn('header_image');
        });
    }
};
