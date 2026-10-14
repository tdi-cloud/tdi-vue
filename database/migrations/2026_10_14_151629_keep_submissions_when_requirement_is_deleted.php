<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Submissions survive the deletion of their requirement, and can also be
     * made without any requirement (an "other document"). `document_title`
     * keeps a copy of the requirement title so the record still has a label.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('document_title')->nullable()->after('requirement_id');
        });

        DB::table('submissions')
            ->whereNotNull('requirement_id')
            ->update([
                'document_title' => DB::raw('(SELECT requirements.title FROM requirements WHERE requirements.id = submissions.requirement_id)'),
            ]);

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropForeign(['requirement_id']);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('requirement_id')->nullable()->change();
            $table->foreign('requirement_id')->references('id')->on('requirements')->nullOnDelete();
        });
    }

    /**
     * Restoring the cascade requires dropping the submissions that no longer
     * belong to a requirement.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropForeign(['requirement_id']);
        });

        DB::table('submissions')->whereNull('requirement_id')->delete();

        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('requirement_id')->nullable(false)->change();
            $table->foreign('requirement_id')->references('id')->on('requirements')->cascadeOnDelete();
            $table->dropColumn('document_title');
        });
    }
};
