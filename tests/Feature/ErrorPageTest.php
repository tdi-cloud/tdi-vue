<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Route::get('/__error-page-test/{status}', fn (int $status) => abort($status));
    Route::get('/__error-page-test-crash', fn () => throw new RuntimeException('secret-internal-detail'));
});

test('failed page visits render the friendly Error page when debug is off', function (int $status) {
    config(['app.debug' => false]);

    $this->get("/__error-page-test/{$status}")
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', $status));
})->with([403, 404, 429, 500, 503]);

test('unknown URLs render the Error page with a 404 status', function () {
    config(['app.debug' => false]);

    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
});

test('server errors never expose exception details', function () {
    config(['app.debug' => false]);

    $this->get('/__error-page-test-crash')
        ->assertStatus(500)
        ->assertDontSee('secret-internal-detail')
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 500));
});

test('JSON requests keep their normal error response', function () {
    config(['app.debug' => false]);

    $this->getJson('/__error-page-test/404')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('debug mode keeps the default error response', function () {
    config(['app.debug' => true]);

    $this->get('/__error-page-test/404')
        ->assertNotFound()
        ->assertDontSee('&quot;component&quot;:&quot;Error&quot;', false);
});
