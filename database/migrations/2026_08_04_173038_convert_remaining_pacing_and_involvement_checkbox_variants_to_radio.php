<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The earlier exact-label migration missed rows whose label text had
     * drifted slightly (extra space before the colon, or a missing "b./c."
     * prefix from an earlier manual edit). Catch those remaining `checkbox`
     * rows by their distinctive substring instead of an exact match.
     */
    public function up(): void
    {
        DB::table('evaluation_questions')
            ->where('type', 'checkbox')
            ->where('label', 'like', '%pacing of the program%')
            ->update(['type' => 'radio']);

        DB::table('evaluation_questions')
            ->where('type', 'checkbox')
            ->where('label', 'like', '%degree of involvement of the participants%')
            ->update(['type' => 'radio']);
    }

    public function down(): void
    {
        DB::table('evaluation_questions')
            ->where('type', 'radio')
            ->where('label', 'like', '%pacing of the program%')
            ->update(['type' => 'checkbox']);

        DB::table('evaluation_questions')
            ->where('type', 'radio')
            ->where('label', 'like', '%degree of involvement of the participants%')
            ->update(['type' => 'checkbox']);
    }
};
