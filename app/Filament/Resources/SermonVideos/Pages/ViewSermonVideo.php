<?php

namespace App\Filament\Resources\SermonVideos\Pages;

use App\Filament\Resources\SermonVideos\SermonVideoResource;
use App\Models\SermonVideo;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

/**
 * @extends ViewRecord<SermonVideo>
 *
 * @method SermonVideo getRecord()
 */
class ViewSermonVideo extends ViewRecord
{
    protected static string $resource = SermonVideoResource::class;

    protected string $view = 'filament.resources.sermon-videos.view-sermon-video';

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title
            ?? $this->getRecord()->date->timezone('America/Chicago')->format('M j, Y g:i A');
    }

    /**
     * @return array{segments: list<array{start: float, end: float, text: string}>, clips: list<array{id: int, start: int, end: int}>}
     */
    #[Computed]
    public function transcriptData(): array
    {
        $segments = collect($this->getRecord()->transcript['segments'] ?? [])
            ->map(fn (array $s): array => [
                'start' => $s['start'],
                'end' => $s['end'],
                'text' => trim($s['text']),
            ])->values()->all();

        return [
            'segments' => $segments,
            'clips' => $this->getClips(),
        ];
    }

    /**
     * @return list<array{id: int, start: int, end: int}>
     */
    public function createClip(int $startSegmentIndex, int $endSegmentIndex): array
    {
        // Ensure start <= end
        if ($startSegmentIndex > $endSegmentIndex) {
            Log::warning('createClip called with start > end, swapping', [
                'sermon_video_id' => $this->getRecord()->id,
                'start_segment_index' => $startSegmentIndex,
                'end_segment_index' => $endSegmentIndex,
            ]);
            [$startSegmentIndex, $endSegmentIndex] = [$endSegmentIndex, $startSegmentIndex];
        }

        // Reject if start is within an existing clip
        $startInClip = $this->getRecord()->sermonClips()
            ->where('start_segment_index', '<=', $startSegmentIndex)
            ->where('end_segment_index', '>=', $startSegmentIndex)
            ->exists();

        if ($startInClip) {
            Log::error('createClip called with start inside an existing clip', [
                'sermon_video_id' => $this->getRecord()->id,
                'start_segment_index' => $startSegmentIndex,
                'end_segment_index' => $endSegmentIndex,
            ]);

            return $this->getClips();
        }

        // Reject if clip would exceed 90s
        $segments = $this->getRecord()->transcript['segments'] ?? [];
        if (isset($segments[$startSegmentIndex], $segments[$endSegmentIndex])) {
            $duration = $segments[$endSegmentIndex]['end'] - $segments[$startSegmentIndex]['start'];
            if ($duration > 90) {
                Log::warning('createClip rejected: duration exceeds 90s', [
                    'sermon_video_id' => $this->getRecord()->id,
                    'start_segment_index' => $startSegmentIndex,
                    'end_segment_index' => $endSegmentIndex,
                    'duration' => $duration,
                ]);

                return $this->getClips();
            }
        }

        // Truncate end if it would overlap into a following clip
        $nextClipStart = $this->getRecord()->sermonClips()
            ->where('start_segment_index', '>', $startSegmentIndex)
            ->where('start_segment_index', '<=', $endSegmentIndex)
            ->min('start_segment_index');

        if ($nextClipStart !== null) {
            Log::warning('createClip truncating end to avoid overlapping a following clip', [
                'sermon_video_id' => $this->getRecord()->id,
                'start_segment_index' => $startSegmentIndex,
                'original_end_segment_index' => $endSegmentIndex,
                'truncated_end_segment_index' => $nextClipStart - 1,
            ]);
            $endSegmentIndex = $nextClipStart - 1;
        }

        $this->getRecord()->sermonClips()->create([
            'start_segment_index' => $startSegmentIndex,
            'end_segment_index' => $endSegmentIndex,
        ]);

        unset($this->transcriptData);

        return $this->getClips();
    }

    /**
     * @return list<array{id: int, start: int, end: int}>
     */
    public function updateClip(int $clipId, int $startSegmentIndex, int $endSegmentIndex): array
    {
        $clip = $this->getRecord()->sermonClips()->findOrFail($clipId);

        // Ensure start <= end
        if ($startSegmentIndex > $endSegmentIndex) {
            [$startSegmentIndex, $endSegmentIndex] = [$endSegmentIndex, $startSegmentIndex];
        }

        // Reject if clip would exceed 90s
        $segments = $this->getRecord()->transcript['segments'] ?? [];
        if (isset($segments[$startSegmentIndex], $segments[$endSegmentIndex])) {
            $duration = $segments[$endSegmentIndex]['end'] - $segments[$startSegmentIndex]['start'];
            if ($duration > 90) {
                Log::warning('updateClip rejected: duration exceeds 90s', [
                    'clip_id' => $clipId,
                    'duration' => $duration,
                ]);

                return $this->getClips();
            }
        }

        // Check no overlap with other clips
        $overlapping = $this->getRecord()->sermonClips()
            ->where('id', '!=', $clipId)
            ->where('start_segment_index', '<=', $endSegmentIndex)
            ->where('end_segment_index', '>=', $startSegmentIndex)
            ->exists();

        if ($overlapping) {
            Log::warning('updateClip rejected: overlaps with another clip', [
                'clip_id' => $clipId,
                'start_segment_index' => $startSegmentIndex,
                'end_segment_index' => $endSegmentIndex,
            ]);

            return $this->getClips();
        }

        $clip->update([
            'start_segment_index' => $startSegmentIndex,
            'end_segment_index' => $endSegmentIndex,
        ]);

        unset($this->transcriptData);

        return $this->getClips();
    }

    /**
     * @return list<array{id: int, start: int, end: int}>
     */
    private function getClips(): array
    {
        return $this->getRecord()->sermonClips()
            ->orderBy('start_segment_index')
            ->get(['id', 'start_segment_index', 'end_segment_index'])
            ->map(fn ($c): array => [
                'id' => $c->id,
                'start' => $c->start_segment_index,
                'end' => $c->end_segment_index,
            ])->values()->all();
    }
}
