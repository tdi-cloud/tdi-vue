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
        // Ang status ay dati enum sa DB level (kailangan mag-migrate bawat
        // bagong status). Gawin nalang itong plain string dahil hindi naman
        // ito pinipilit i-restrict sa application layer (walang Rule::in()),
        // para hindi na kailangan pang mag-alter ng column sa susunod na
        // pagdaragdag ng status (hal. "not_nfp_concern").
        Schema::table('foreign_programs', function (Blueprint $table) {
            $table->string('status', 50)->default('for_dissemination')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foreign_programs', function (Blueprint $table) {
            $table->enum('status', [
                'for_dissemination',
                'waiting_for_nominees',
                'for_interview',
                'for_endorsement',
                'no_nominee',
                'waiting_for_result',
                'ongoing',
                'concluded',
            ])->default('for_dissemination')->change();
        });
    }
};
