<?php

use App\Models\User;
use Laravel\Dusk\Browser;

test('unauthenticated user is redirected to login page', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
            ->waitForLocation('/login')
            ->assertPathIs('/login')
            ->assertSee('Sign in');
    });
});

test('user can log in with valid credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->waitFor('input[type="email"]')
            ->type('input[type="email"]', $user->email)
            ->type('input[type="password"]', 'password')
            ->press('Sign in')
            ->waitForLocation('/')
            ->assertPathIs('/');
    });
});

test('user cannot log in with invalid credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->waitFor('input[type="email"]')
            ->type('input[type="email"]', $user->email)
            ->type('input[type="password"]', 'wrong-password')
            ->press('Sign in')
            ->waitForText('These credentials do not match our records')
            ->assertSee('These credentials do not match our records');
    });
});
