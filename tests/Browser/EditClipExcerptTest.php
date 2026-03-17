<?php

use App\Enums\JobStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoClip;
use Laravel\Dusk\Browser;

test('typing in the excerpt field preserves all characters after debounce', function () {
    $user = User::factory()->create();

    $video = Video::factory()->create([
        'transcript_status' => JobStatus::Completed,
        'duration' => 120,
        'transcript' => [
            'segments' => [
                ['start' => 0.0, 'end' => 8.0, 'text' => 'Welcome to today\'s sermon.', 'words' => [
                    ['word' => 'Welcome', 'start' => 0.0, 'end' => 0.5],
                    ['word' => 'to', 'start' => 0.5, 'end' => 0.8],
                    ['word' => 'today\'s', 'start' => 0.8, 'end' => 1.2],
                    ['word' => 'sermon.', 'start' => 1.2, 'end' => 2.0],
                ]],
                ['start' => 8.5, 'end' => 16.0, 'text' => 'Let us begin with a reading.', 'words' => [
                    ['word' => 'Let', 'start' => 8.5, 'end' => 8.8],
                    ['word' => 'us', 'start' => 8.8, 'end' => 9.0],
                    ['word' => 'begin', 'start' => 9.0, 'end' => 9.5],
                    ['word' => 'with', 'start' => 9.5, 'end' => 9.8],
                    ['word' => 'a', 'start' => 9.8, 'end' => 10.0],
                    ['word' => 'reading.', 'start' => 10.0, 'end' => 10.5],
                ]],
                ['start' => 16.5, 'end' => 24.0, 'text' => 'The passage speaks of hope.', 'words' => [
                    ['word' => 'The', 'start' => 16.5, 'end' => 16.8],
                    ['word' => 'passage', 'start' => 16.8, 'end' => 17.2],
                    ['word' => 'speaks', 'start' => 17.2, 'end' => 17.5],
                    ['word' => 'of', 'start' => 17.5, 'end' => 17.7],
                    ['word' => 'hope.', 'start' => 17.7, 'end' => 18.0],
                ]],
                ['start' => 24.5, 'end' => 32.0, 'text' => 'Hope is central to our faith.', 'words' => [
                    ['word' => 'Hope', 'start' => 24.5, 'end' => 24.8],
                    ['word' => 'is', 'start' => 24.8, 'end' => 25.0],
                    ['word' => 'central', 'start' => 25.0, 'end' => 25.5],
                    ['word' => 'to', 'start' => 25.5, 'end' => 25.7],
                    ['word' => 'our', 'start' => 25.7, 'end' => 25.9],
                    ['word' => 'faith.', 'start' => 25.9, 'end' => 26.5],
                ]],
                ['start' => 32.5, 'end' => 40.0, 'text' => 'We see it reflected in scripture.', 'words' => [
                    ['word' => 'We', 'start' => 32.5, 'end' => 32.8],
                    ['word' => 'see', 'start' => 32.8, 'end' => 33.0],
                    ['word' => 'it', 'start' => 33.0, 'end' => 33.2],
                    ['word' => 'reflected', 'start' => 33.2, 'end' => 33.8],
                    ['word' => 'in', 'start' => 33.8, 'end' => 34.0],
                    ['word' => 'scripture.', 'start' => 34.0, 'end' => 34.5],
                ]],
                ['start' => 40.5, 'end' => 48.0, 'text' => 'And in the lives of believers.', 'words' => [
                    ['word' => 'And', 'start' => 40.5, 'end' => 40.8],
                    ['word' => 'in', 'start' => 40.8, 'end' => 41.0],
                    ['word' => 'the', 'start' => 41.0, 'end' => 41.2],
                    ['word' => 'lives', 'start' => 41.2, 'end' => 41.5],
                    ['word' => 'of', 'start' => 41.5, 'end' => 41.7],
                    ['word' => 'believers.', 'start' => 41.7, 'end' => 42.5],
                ]],
            ],
        ],
    ]);

    $clip = VideoClip::factory()->create([
        'video_id' => $video->id,
        'start_segment_index' => 0,
        'end_segment_index' => 5,
        'excerpt' => 'Original excerpt text.',
    ]);

    $this->browse(function (Browser $browser) use ($user, $clip) {
        $browser->loginAs($user)
            ->visit("/clips/{$clip->id}/edit")
            ->waitForTextIn('h1', $clip->title ?? '');

        $excerptSelector = 'textarea[wire\\:model\\.blur="data.excerpt"]';
        $browser->waitFor($excerptSelector);

        // Clear the field and type text with a pause in the middle that would
        // have triggered the old live(debounce: 500) Livewire round-trip.
        $browser->clear($excerptSelector)
            ->type($excerptSelector, 'Hello')
            ->pause(800) // Would have triggered 500ms debounce round-trip
            ->append($excerptSelector, ' World')
            ->pause(800);

        // With the old `live(debounce: 500)`, the Livewire round-trip after
        // typing "Hello" would reset the textarea, causing " World" to be lost
        // or to appear as the only content. With `live(onBlur: true)`, no
        // round-trip happens until focus leaves the field, so text is preserved.
        $value = $browser->value($excerptSelector);
        expect($value)->toBe('Hello World');
    });
});
