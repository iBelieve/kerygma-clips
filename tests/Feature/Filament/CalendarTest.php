<?php

use App\Enums\ClipStatus;
use App\Filament\Pages\Calendar;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoClip;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function createVideoWithClips(int $clipCount = 3): array
{
    $segments = [];
    for ($i = 0; $i < 20; $i++) {
        $segments[] = [
            'start' => $i * 5.0,
            'end' => $i * 5.0 + 5.0,
            'text' => "Segment {$i}",
        ];
    }

    $video = Video::factory()->create([
        'transcript' => ['segments' => $segments],
    ]);

    $clips = [];
    for ($i = 0; $i < $clipCount; $i++) {
        $clips[] = $video->videoClips()->create([
            'start_segment_index' => $i * 2,
            'end_segment_index' => $i * 2 + 1,
            'title' => "Clip {$i}",
        ]);
    }

    return [$video, $clips];
}

test('it can render the calendar page', function () {
    Livewire::test(Calendar::class)
        ->assertSuccessful();
});

test('it can schedule a clip', function () {
    [$video, $clips] = createVideoWithClips(1);
    $clip = $clips[0];

    Livewire::test(Calendar::class)
        ->call('scheduleClip', $clip->id, '2026-03-15');

    expect($clip->fresh()->scheduled_date->toDateString())
        ->toBe('2026-03-15');
});

test('it can unschedule a clip', function () {
    [$video, $clips] = createVideoWithClips(1);
    $clip = $clips[0];

    // Schedule first via query builder (same as page does)
    \App\Models\VideoClip::where('id', $clip->id)->update(['scheduled_date' => '2026-03-15']);

    Livewire::test(Calendar::class)
        ->call('unscheduleClip', $clip->id);

    expect($clip->fresh()->scheduled_date)->toBeNull();
});

test('it can navigate months', function () {
    $now = now();

    Livewire::test(Calendar::class)
        ->assertSet('month', $now->month)
        ->assertSet('year', $now->year)
        ->call('nextMonth')
        ->assertSet('month', $now->copy()->addMonth()->month)
        ->call('previousMonth')
        ->assertSet('month', $now->month);
});

test('it includes lectionary day names from the API', function () {
    Http::fake([
        'mluther.org/api/lectionary/2026/3' => Http::response([
            'data' => [
                [
                    'date' => '2026-03-01',
                    'short_name' => 'Lent 2',
                    'color_key' => 'purple',
                ],
                [
                    'date' => '2026-03-08',
                    'short_name' => 'Lent 3',
                    'color_key' => 'purple',
                ],
            ],
        ]),
        'mluther.org/api/lectionary/*' => Http::response(['data' => []]),
    ]);

    $component = Livewire::test(Calendar::class)
        ->set('year', 2026)
        ->set('month', 3);

    $days = $component->instance()->calendarDays;
    $march1 = collect($days)->firstWhere('date', '2026-03-01');
    $march2 = collect($days)->firstWhere('date', '2026-03-02');

    expect($march1['lectionaryName'])->toBe('Lent 2')
        ->and($march1['lectionaryColor'])->toBe('#66479c')
        ->and($march2['lectionaryName'])->toBeNull();
});

test('it handles lectionary API failure gracefully', function () {
    Http::fake([
        'mluther.org/api/lectionary/*' => Http::response(null, 500),
    ]);

    Livewire::test(Calendar::class)
        ->assertSuccessful();
});

test('unscheduled clips sidebar only shows approved clips', function () {
    [$video, $clips] = createVideoWithClips(2);

    // One approved, one draft (default)
    VideoClip::where('id', $clips[0]->id)->update(['status' => ClipStatus::Approved]);

    $component = Livewire::test(Calendar::class);
    $unscheduled = $component->instance()->unscheduledClips;

    expect($unscheduled)->toHaveCount(1)
        ->and($unscheduled->first()->id)->toBe($clips[0]->id);
});

test('scheduled clips on calendar include both draft and approved clips', function () {
    [$video, $clips] = createVideoWithClips(2);
    $now = now();

    $date = $now->format('Y-m').'-15';
    VideoClip::where('id', $clips[0]->id)->update([
        'scheduled_date' => $date,
        'status' => ClipStatus::Approved,
    ]);
    VideoClip::where('id', $clips[1]->id)->update([
        'scheduled_date' => $date,
        'status' => ClipStatus::Draft,
    ]);

    $component = Livewire::test(Calendar::class)
        ->set('year', $now->year)
        ->set('month', $now->month);

    $scheduled = $component->instance()->scheduledClips;

    expect($scheduled)->toHaveCount(2);
});

test('unscheduled clips are sorted by video date then clip start time', function () {
    $segments = [];
    for ($i = 0; $i < 20; $i++) {
        $segments[] = [
            'start' => $i * 5.0,
            'end' => $i * 5.0 + 5.0,
            'text' => "Segment {$i}",
        ];
    }

    // Create an older video with two clips (later clip created first)
    $olderVideo = Video::factory()->create([
        'date' => '2026-01-01',
        'transcript' => ['segments' => $segments],
    ]);
    $olderClipB = $olderVideo->videoClips()->create([
        'start_segment_index' => 4,
        'end_segment_index' => 5,
        'title' => 'Older Video - Later Clip',
        'status' => ClipStatus::Approved,
    ]);
    $olderClipA = $olderVideo->videoClips()->create([
        'start_segment_index' => 0,
        'end_segment_index' => 1,
        'title' => 'Older Video - Earlier Clip',
        'status' => ClipStatus::Approved,
    ]);

    // Create a newer video with one clip
    $newerVideo = Video::factory()->create([
        'date' => '2026-02-15',
        'transcript' => ['segments' => $segments],
    ]);
    $newerClip = $newerVideo->videoClips()->create([
        'start_segment_index' => 2,
        'end_segment_index' => 3,
        'title' => 'Newer Video Clip',
        'status' => ClipStatus::Approved,
    ]);

    $component = Livewire::test(Calendar::class);
    $unscheduled = $component->instance()->unscheduledClips;

    expect($unscheduled)->toHaveCount(3)
        ->and($unscheduled[0]->title)->toBe('Older Video - Earlier Clip')
        ->and($unscheduled[1]->title)->toBe('Older Video - Later Clip')
        ->and($unscheduled[2]->title)->toBe('Newer Video Clip');
});

test('it can navigate to today', function () {
    $now = now();

    Livewire::test(Calendar::class)
        ->call('nextMonth')
        ->call('nextMonth')
        ->call('goToToday')
        ->assertSet('month', $now->month)
        ->assertSet('year', $now->year);
});
