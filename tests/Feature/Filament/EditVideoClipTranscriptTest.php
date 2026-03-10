<?php

use App\Enums\JobStatus;
use App\Filament\Resources\VideoClips\Pages\EditVideoClip;
use App\Jobs\ExtractVideoClipVerticalVideo;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Build a transcript with evenly spaced segments that include word-level data.
 *
 * @return array{segments: list<array{start: float, end: float, text: string, words: list<array{word: string, start: float, end: float, score: float}>}>, word_segments: list<array{word: string, start: float, end: float, score: float}>}
 */
function makeTranscriptWithWords(int $segmentCount = 5): array
{
    $segments = [];
    $wordSegments = [];

    for ($i = 0; $i < $segmentCount; $i++) {
        $segStart = $i * 10.0;
        $words = [
            ['word' => 'Word', 'start' => $segStart, 'end' => $segStart + 1.0, 'score' => 0.9],
            ['word' => 'number', 'start' => $segStart + 2.0, 'end' => $segStart + 3.0, 'score' => 0.85],
            ['word' => (string) $i, 'start' => $segStart + 4.0, 'end' => $segStart + 5.0, 'score' => 0.8],
        ];

        $segments[] = [
            'start' => $segStart,
            'end' => $segStart + 5.0,
            'text' => ' Word number '.$i,
            'words' => $words,
        ];

        array_push($wordSegments, ...$words);
    }

    return ['segments' => $segments, 'word_segments' => $wordSegments];
}

function makeVideoWithWords(int $segmentCount = 5): Video
{
    return Video::factory()->create([
        'transcript' => makeTranscriptWithWords($segmentCount),
        'duration' => $segmentCount * 10,
    ]);
}

test('transcriptRows includes segmentIndex and words for each segment', function () {
    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 1,
        'end_segment_index' => 3,
    ]);

    $component = Livewire::test(EditVideoClip::class, ['record' => $clip->id]);
    $rows = $component->instance()->transcriptRows;

    $segmentRows = array_values(array_filter($rows, fn ($r) => $r['type'] === 'segment'));

    expect($segmentRows)->toHaveCount(3);
    expect($segmentRows[0]['segmentIndex'])->toBe(1);
    expect($segmentRows[1]['segmentIndex'])->toBe(2);
    expect($segmentRows[2]['segmentIndex'])->toBe(3);
    expect($segmentRows[0]['words'])->toHaveCount(3);
    expect($segmentRows[0]['words'][0]['word'])->toBe('Word');
});

test('updateSegmentWords saves edited word text', function () {
    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 1,
        'end_segment_index' => 3,
    ]);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 2, ['Hello', 'world', '2']);

    $transcript = $video->refresh()->transcript;
    expect($transcript['segments'][2]['words'][0]['word'])->toBe('Hello');
    expect($transcript['segments'][2]['words'][1]['word'])->toBe('world');
    expect($transcript['segments'][2]['words'][2]['word'])->toBe('2');
});

test('updateSegmentWords rebuilds segment text from words', function () {
    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
    ]);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 1, ['"Grace', 'and', 'mercy."']);

    $transcript = $video->refresh()->transcript;
    expect($transcript['segments'][1]['text'])->toBe('"Grace and mercy."');
});

test('updateSegmentWords syncs word_segments entries', function () {
    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
    ]);

    // Segment 1 starts at 10.0; its first word starts at 10.0
    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 1, ['Updated', 'words', 'here']);

    $transcript = $video->refresh()->transcript;

    // word_segments entries with matching start times should be updated
    $wsAt10 = collect($transcript['word_segments'])->firstWhere('start', 10.0);
    $wsAt12 = collect($transcript['word_segments'])->firstWhere('start', 12.0);
    $wsAt14 = collect($transcript['word_segments'])->firstWhere('start', 14.0);

    expect($wsAt10['word'])->toBe('Updated');
    expect($wsAt12['word'])->toBe('words');
    expect($wsAt14['word'])->toBe('here');
});

test('updateSegmentWords rejects segment index outside clip bounds', function () {
    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 1,
        'end_segment_index' => 3,
    ]);

    $originalTranscript = $video->transcript;

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 0, ['Out', 'of', 'bounds']);

    expect($video->refresh()->transcript)->toBe($originalTranscript);
});

test('updateSegmentWords rejects mismatched word count', function () {
    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
    ]);

    $originalTranscript = $video->transcript;

    // Segment has 3 words, we pass 2
    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 1, ['Only', 'two']);

    expect($video->refresh()->transcript)->toBe($originalTranscript);
});

test('updateSegmentWords rejects empty word text', function () {
    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
    ]);

    $originalTranscript = $video->transcript;

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 1, ['Word', '', '1']);

    expect($video->refresh()->transcript)->toBe($originalTranscript);
});

test('updateSegmentWords dispatches re-export when clip video is completed', function () {
    Queue::fake();

    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
        'clip_video_status' => JobStatus::Completed,
    ]);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 1, ['Changed', 'the', 'words']);

    Queue::assertPushed(ExtractVideoClipVerticalVideo::class, function ($job) use ($clip) {
        return $job->videoClip->id === $clip->id;
    });
});

test('updateSegmentWords dispatches re-export when clip video is processing', function () {
    Queue::fake();

    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
        'clip_video_status' => JobStatus::Processing,
    ]);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 1, ['Changed', 'the', 'words']);

    Queue::assertPushed(ExtractVideoClipVerticalVideo::class, function ($job) use ($clip) {
        return $job->videoClip->id === $clip->id;
    });
});

test('updateSegmentWords does not dispatch re-export for non-exportable statuses', function (
    ?JobStatus $status
) {
    Queue::fake();

    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
        'clip_video_status' => $status,
    ]);

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 1, ['Changed', 'the', 'words']);

    Queue::assertNotPushed(ExtractVideoClipVerticalVideo::class);
})->with([
    'pending' => [JobStatus::Pending],
    'failed' => [JobStatus::Failed],
    'timed out' => [JobStatus::TimedOut],
]);

test('updateSegmentWords does not dispatch re-export when validation fails', function () {
    Queue::fake();

    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
        'clip_video_status' => JobStatus::Completed,
    ]);

    // Mismatched word count - should fail validation and not dispatch
    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 1, ['Only', 'two']);

    Queue::assertNotPushed(ExtractVideoClipVerticalVideo::class);
});

test('updateSegmentWords preserves word timestamps', function () {
    $video = makeVideoWithWords();
    $clip = $video->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 2,
    ]);

    $originalStart = $video->transcript['segments'][1]['words'][0]['start'];
    $originalEnd = $video->transcript['segments'][1]['words'][0]['end'];
    $originalScore = $video->transcript['segments'][1]['words'][0]['score'];

    Livewire::test(EditVideoClip::class, ['record' => $clip->id])
        ->call('updateSegmentWords', 1, ['Changed', 'the', 'words']);

    $transcript = $video->refresh()->transcript;
    expect($transcript['segments'][1]['words'][0]['start'])->toBe($originalStart);
    expect($transcript['segments'][1]['words'][0]['end'])->toBe($originalEnd);
    expect($transcript['segments'][1]['words'][0]['score'])->toBe($originalScore);
});
