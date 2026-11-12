<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The banner texts an admin can change on a public form: the title, the
     * short line above it and the description. Null means the form's default
     * text (defined in code with its questions).
     */
    public function up(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            $table->string('title')->nullable()->after('slug');
            $table->string('kicker')->nullable()->after('title');
            $table->text('description')->nullable()->after('kicker');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_forms', function (Blueprint $table) {
            $table->dropColumn(['title', 'kicker', 'description']);
        });
    }
};
