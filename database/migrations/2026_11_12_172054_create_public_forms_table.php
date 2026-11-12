<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings of the public forms (e.g. the Learning and Development
     * Survey): open/closed and the background picture. The questions
     * themselves are defined in code (App\Support\PublicForms); a form
     * without a row here is open and uses the site-wide default background.
     */
    public function up(): void
    {
        Schema::create('public_forms', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_open')->default(true);
            $table->timestamp('closes_at')->nullable();
            $table->string('background_image')->nullable();
            $table->boolean('hide_background')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_forms');
    }
};
