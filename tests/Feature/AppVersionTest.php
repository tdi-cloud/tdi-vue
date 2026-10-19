<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the application version is shared with every page', function () {
    $this->actingAs(User::factory()->create(['empcode' => 'EMP-VERSION-01']));

    $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
        ->where('appVersion', '1.0.0')
    );
});
