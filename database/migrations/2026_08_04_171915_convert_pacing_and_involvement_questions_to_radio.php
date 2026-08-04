<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * These two questions were originally seeded as `checkbox` (multi-select),
     * which incorrectly let respondents pick more than one option. Convert
     * any already-created rows (across all existing evaluation forms) to
     * `radio` (single-select) to match the corrected default template.
     */
    public function up(): void
    {
        DB::table('evaluation_questions')
            ->where('type', 'checkbox')
            ->whereIn('label', [
                'b. The pacing of the program is:',
                'c. The degree of involvement of the participants is:',
            ])
            ->update(['type' => 'radio']);
    }

    public function down(): void
    {
        DB::table('evaluation_questions')
            ->where('type', 'radio')
            ->whereIn('label', [
                'b. The pacing of the program is:',
                'c. The degree of involvement of the participants is:',
            ])
            ->update(['type' => 'checkbox']);
    }
};
