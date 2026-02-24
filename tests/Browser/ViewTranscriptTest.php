<?php

use App\Enums\TranscriptStatus;
use App\Models\SermonClip;
use App\Models\SermonVideo;
use App\Models\User;
use Laravel\Dusk\Browser;

test('hovering a segment highlights it and clicking creates a clip', function () {
    $user = User::factory()->create();

    $sermonVideo = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Completed,
        'duration' => 120,
        'transcript' => [
            'segments' => [
                ['start' => 0.0, 'end' => 8.0, 'text' => 'Welcome to today\'s sermon.'],
                ['start' => 8.5, 'end' => 16.0, 'text' => 'Let us begin with a reading.'],
                ['start' => 16.5, 'end' => 24.0, 'text' => 'The passage speaks of hope.'],
                ['start' => 24.5, 'end' => 32.0, 'text' => 'Hope is central to our faith.'],
                ['start' => 32.5, 'end' => 40.0, 'text' => 'We see it reflected in scripture.'],
                ['start' => 40.5, 'end' => 48.0, 'text' => 'And in the lives of believers.'],
                ['start' => 48.5, 'end' => 56.0, 'text' => 'Consider the example of Abraham.'],
                ['start' => 56.5, 'end' => 64.0, 'text' => 'He trusted God\'s promise.'],
                ['start' => 64.5, 'end' => 72.0, 'text' => 'Even when it seemed impossible.'],
                ['start' => 72.5, 'end' => 80.0, 'text' => 'That is the nature of true faith.'],
                ['start' => 80.5, 'end' => 88.0, 'text' => 'It perseveres through trials.'],
                ['start' => 88.5, 'end' => 96.0, 'text' => 'And holds fast to the promise.'],
                ['start' => 96.5, 'end' => 104.0, 'text' => 'Let us carry this forward.'],
                ['start' => 104.5, 'end' => 112.0, 'text' => 'Into our daily lives.'],
                ['start' => 112.5, 'end' => 120.0, 'text' => 'Thank you and amen.'],
            ],
        ],
    ]);

    $this->browse(function (Browser $browser) use ($user, $sermonVideo) {
        $browser->loginAs($user)
            ->visit("/sermon-videos/{$sermonVideo->id}")
            ->waitFor('@transcript-table')
            ->assertSeeIn('@transcript-table', 'Welcome to today\'s sermon.')
            ->assertSeeIn('@transcript-table', 'Let us begin with a reading.');

        // Verify no segments are highlighted initially (no orange or emerald classes)
        $browser->assertMissing('[dusk="segment-row-0"].bg-orange-100')
            ->assertMissing('[dusk="segment-row-0"].bg-emerald-100');

        // Hover over the first segment row to trigger highlight
        $browser->mouseover('@segment-row-0')
            ->pause(200);

        // Verify the hovered segment has the orange highlight class
        $hasHighlight = $browser->script(
            "return document.querySelector('[dusk=\"segment-row-0\"]').classList.contains('bg-orange-100')"
        );
        expect($hasHighlight[0])->toBeTrue();

        // Click to create a clip from the highlighted segment
        $browser->click('@segment-row-0')
            ->pause(500);

        // Verify the segment now has the emerald (clip) background class
        $browser->waitUntil(
            "document.querySelector('[dusk=\"segment-row-0\"]').classList.contains('bg-emerald-100')",
            5
        );
    });

    // Verify the clip was persisted in the database
    expect(SermonClip::where('sermon_video_id', $sermonVideo->id)->count())->toBe(1);

    $clip = SermonClip::where('sermon_video_id', $sermonVideo->id)->first();
    expect($clip->start_segment_index)->toBe(0);
});
