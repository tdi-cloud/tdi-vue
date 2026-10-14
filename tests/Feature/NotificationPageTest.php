<?php

use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function notificationPageUser(string $empcode, string $access = 'user'): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => $access]);
}

function giveNotification(User $user, string $title, bool $read = false, ?string $url = null): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['title' => $title, 'message' => "Message for {$title}", 'url' => $url],
        'read_at' => $read ? now() : null,
    ]);
}

test('guests are redirected to login from the notifications page', function () {
    $this->get(route('notifications.page'))->assertRedirect(route('login'));
});

test('the notifications page lists only the signed-in user\'s notifications with counts', function () {
    $user = notificationPageUser('EMP-NP-01');
    $other = notificationPageUser('EMP-NP-02');

    giveNotification($user, 'Unread one');
    giveNotification($user, 'Already read', read: true, url: '/my-programs');
    giveNotification($other, 'Someone else');

    $this->actingAs($user)
        ->get(route('notifications.page'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('filter', 'all')
            ->where('counts.all', 2)
            ->where('counts.unread', 1)
            ->has('notifications.data', 2)
            ->has('notifications.data.0', fn (Assert $row) => $row
                ->hasAll(['id', 'title', 'message', 'url', 'read', 'created_at', 'created_at_full'])
                ->etc()
            )
        );
});

test('the unread filter shows only unread notifications', function () {
    $user = notificationPageUser('EMP-NP-03');
    giveNotification($user, 'Unread one');
    giveNotification($user, 'Already read', read: true);

    $this->actingAs($user)
        ->get(route('notifications.page', ['filter' => 'unread']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter', 'unread')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Unread one')
            ->where('notifications.data.0.read', false)
        );
});

test('the notifications page is paginated', function () {
    $user = notificationPageUser('EMP-NP-04');
    foreach (range(1, 17) as $i) {
        giveNotification($user, "Notification {$i}");
    }

    $this->actingAs($user)
        ->get(route('notifications.page'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', 15)
            ->where('notifications.last_page', 2)
            ->where('counts.all', 17)
        );
});

test('admins can open the notifications page too', function () {
    $admin = notificationPageUser('EMP-NP-05', 'admin');
    giveNotification($admin, 'For the admin');

    $this->actingAs($admin)
        ->get(route('notifications.page'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Notifications/Index')->has('notifications.data', 1));
});
