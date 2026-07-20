<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ang "Added to a Program" notification ay dating tumuturo sa admin
     * monitoring page (programs.show, admin-gated) — na-ayos na ito sa code
     * papuntang enrolled programs list, pero ang mga notification na
     * nadeliver/naka-queue na bago ang ayos ay naka-freeze pa rin sa lumang
     * URL (naka-save bilang JSON snapshot). I-backfill dito ang mga iyon.
     */
    public function up(): void
    {
        $url = route('home').'#my-programs';

        DB::table('notifications')
            ->where('type', 'App\\Notifications\\ParticipantAdded')
            ->get()
            ->each(function ($row) use ($url) {
                $data = json_decode($row->data, true);
                if (! isset($data['url']) || $data['url'] === $url) {
                    return;
                }
                $data['url'] = $url;
                DB::table('notifications')->where('id', $row->id)->update(['data' => json_encode($data)]);
            });

        if (Schema::hasTable('pending_notifications')) {
            DB::table('pending_notifications')
                ->where('type', 'App\\Notifications\\ParticipantAdded')
                ->get()
                ->each(function ($row) use ($url) {
                    $data = json_decode($row->data, true);
                    if (! isset($data['url']) || $data['url'] === $url) {
                        return;
                    }
                    $data['url'] = $url;
                    DB::table('pending_notifications')->where('id', $row->id)->update(['data' => json_encode($data)]);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data backfill lang ito — walang schema na babaguhin, kaya walang
        // ma-a-undo. Sadyang hindi na-recreate ang lumang (mali) URL.
    }
};
