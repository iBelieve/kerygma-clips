<?php

use App\Models\User;
use App\Models\Video;
use Laravel\Dusk\Browser;

test('authenticated user can see the dashboard', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/')
            ->waitForText('Dashboard')
            ->assertSee('Dashboard');
    });
});

test('authenticated user can navigate to the videos list', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/videos')
            ->waitForText('Videos')
            ->assertSee('Videos')
            ->assertSee('Upload Video');
    });
});

test('videos list displays existing videos', function () {
    $user = User::factory()->create();
    $video = Video::factory()->create([
        'title' => 'Sunday Morning Sermon',
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/videos')
            ->waitForText('Videos')
            ->assertSee('Sunday Morning Sermon');
    });
});
