<?php

use App\Models\User;
use App\Support\LiteMode;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['empcode' => 'EMP-LITE-01', 'access' => 'superadmin']));
});

test('lists keep their normal page size without lite mode', function () {
    expect($this->get(route('submissions.index'))->inertiaProps('submissions')['per_page'])->toBe(20);
});

test('the lite mode cookie makes long lists send fewer rows', function () {
    $response = $this->withUnencryptedCookie(LiteMode::COOKIE, '1')->get(route('submissions.index'));

    expect($response->inertiaProps('submissions')['per_page'])->toBe(LiteMode::PER_PAGE);
});

test('an explicit per_page still wins over lite mode', function () {
    $response = $this->withUnencryptedCookie(LiteMode::COOKIE, '1')->get(route('action-plan-monitoring.index', ['per_page' => 25]));

    expect($response->inertiaProps('activities')['per_page'])->toBe(25);
});
