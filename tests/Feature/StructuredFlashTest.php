<?php

use App\Models\User;
use App\Support\FlashMessage;
use Inertia\Testing\AssertableInertia as Assert;

test('a structured success flash reaches the page with its title, message, next step and action', function () {
    $user = User::factory()->create(['access' => 'admin']);

    $this->actingAs($user)
        ->withSession(['success' => FlashMessage::make(
            'Program created',
            'It is saved under Programs.',
            'Add a batch next.',
            ['label' => 'Open program', 'href' => '/programs/1'],
        )])
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success.title', 'Program created')
            ->where('flash.success.message', 'It is saved under Programs.')
            ->where('flash.success.next', 'Add a batch next.')
            ->where('flash.success.action.label', 'Open program')
            ->where('flash.success.action.href', '/programs/1'));
});

test('a plain-string success flash is still shared as is', function () {
    $user = User::factory()->create(['access' => 'admin']);

    $this->actingAs($user)
        ->withSession(['success' => 'Saved.'])
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('flash.success', 'Saved.'));
});
