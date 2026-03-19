<?php

use App\Enums\JobStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoClip;
use Laravel\Dusk\Browser;

test('clip drag handles do not overflow the transcript table on mobile viewport', function () {
    $user = User::factory()->create();

    $video = Video::factory()->create([
        'transcript_status' => JobStatus::Completed,
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

    // Create a clip so the drag handle bars are visible
    VideoClip::factory()->create([
        'video_id' => $video->id,
        'start_segment_index' => 3,
        'end_segment_index' => 7,
    ]);

    $this->browse(function (Browser $browser) use ($user, $video) {
        // Use a mobile-sized viewport (iPhone-like width)
        $browser->resize(375, 812)
            ->loginAs($user)
            ->visit("/videos/{$video->id}/edit")
            ->waitFor('@transcript-table');

        // Wait for clips to render (emerald background on clip start row)
        $browser->waitUntil(
            "document.querySelector('[dusk=\"segment-row-3\"]').classList.contains('bg-emerald-100')
             || document.querySelector('[dusk=\"segment-row-3\"]').classList.contains('dark:bg-emerald-500/10')",
            5
        );

        // Get the transcript table container width
        $tableContainerWidth = $browser->script(
            "return document.querySelector('[dusk=\"transcript-table\"]').getBoundingClientRect().width"
        )[0];

        // Find all visible drag handle bars (the absolute inset-x-0 divs)
        // They have bg-emerald-400/40 class
        $barWidths = $browser->script(<<<'JS'
            const bars = document.querySelectorAll('.bg-emerald-400\\/40');
            return Array.from(bars)
                .filter(el => el.offsetParent !== null)
                .map(el => el.getBoundingClientRect().width);
        JS)[0];

        // Each drag handle bar should not exceed the table container width
        foreach ($barWidths as $barWidth) {
            expect($barWidth)->toBeLessThanOrEqual($tableContainerWidth);
        }
    });
});
