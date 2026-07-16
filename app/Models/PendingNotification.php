<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PendingNotification extends Model
{
    protected $fillable = [
        'empcode',
        'type',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * I-notify agad ang user kung meron nang account (naka-link sa empcode);
     * kung wala pa, i-queue na lang bilang pending notification para
     * ma-deliver kapag nakapag-register na siya.
     */
    public static function notifyOrQueue(string $empcode, Notification $notification): void
    {
        $user = User::where('empcode', $empcode)->first();

        if ($user) {
            $user->notify($notification);

            return;
        }

        self::create([
            'empcode' => $empcode,
            'type' => get_class($notification),
            // Walang User pang naka-link, kaya walang totoong $notifiable ---
            // pareho ang ParticipantAdded/ParticipantRemoved, hindi
            // ginagamit ang param sa loob ng toArray(), placeholder lang ito
            // para masunod ang `object` type-hint.
            'data' => $notification->toArray(new \stdClass),
        ]);
    }

    /**
     * Ilipat ang lahat ng pending notifications ng isang empcode papunta sa
     * totoong notifications table ng bagong-rehistrong user, tapos burahin
     * ang mga pending record. Tinatawag ito kaagad pagkatapos gawin ang User.
     */
    public static function deliverTo(User $user): void
    {
        $pending = self::where('empcode', $user->empcode)->get();

        foreach ($pending as $notification) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => null,
            ]);
        }

        self::where('empcode', $user->empcode)->delete();
    }
}
