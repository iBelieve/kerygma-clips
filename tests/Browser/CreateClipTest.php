<?php

use App\Enums\TranscriptStatus;
use App\Models\SermonVideo;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;

test('can create clip by clicking transcript segment', function () {
    $segments = [];
    for ($i = 0; $i < 20; $i++) {
        $segments[] = [
            'start' => $i * 6,
            'end' => $i * 6 + 5,
            'text' => "Segment number {$i} of the sermon transcript.",
        ];
    }

    $user = User::factory()->create();

    $sermonVideo = SermonVideo::factory()->create([
        'transcript_status' => TranscriptStatus::Completed,
        'transcript' => ['segments' => $segments],
        'duration' => 119,
    ]);

    $this->browse(function (Browser $browser) use ($user, $sermonVideo) {
        $browser->loginAs($user)
            ->visit("/sermon-videos/{$sermonVideo->id}")
            ->waitForText('Segment number 5 of the sermon transcript.');

        // Click the row containing segment 5 via WebDriver XPath.
        $row = $browser->driver->findElement(
            WebDriverBy::xpath("//td[contains(text(), 'Segment number 5 of the sermon transcript.')]/parent::tr")
        );
        $row->click();

        // Wait for the Livewire round-trip to persist the clip — the row
        // keeps its green background from the optimistic update, but we
        // need the server to have finished writing to the DB.
        $browser->waitUsing(5, 250, function () use ($sermonVideo) {
            return $sermonVideo->sermonClips()->exists();
        });
    });

    $this->assertDatabaseHas('sermon_clips', [
        'sermon_video_id' => $sermonVideo->id,
        'start_segment_index' => 5,
        'end_segment_index' => 14,
    ]);
});
