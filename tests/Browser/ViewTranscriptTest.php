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
                ['start' => 0.0, 'end' => 5.0, 'text' => 'First segment of the sermon.'],
                ['start' => 5.5, 'end' => 10.0, 'text' => 'Second segment of the sermon.'],
                ['start' => 10.5, 'end' => 15.0, 'text' => 'Third segment of the sermon.'],
                ['start' => 15.5, 'end' => 20.0, 'text' => 'Fourth segment of the sermon.'],
                ['start' => 20.5, 'end' => 25.0, 'text' => 'Fifth segment of the sermon.'],
            ],
        ],
    ]);

    $this->browse(function (Browser $browser) use ($user, $sermonVideo) {
        $browser->loginAs($user)
            ->visit("/sermon-videos/{$sermonVideo->id}")
            ->waitFor('@transcript-table')
            ->assertSeeIn('@transcript-table', 'First segment of the sermon.')
            ->assertSeeIn('@transcript-table', 'Second segment of the sermon.');

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
