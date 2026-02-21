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

    public int $gapThreshold = 2;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title
            ?? $this->getRecord()->date->timezone('America/Chicago')->format('M j, Y g:i A');
    }

    /**
     * @return list<array{type: 'segment', start: float, end: float, segmentIndex: int, highlightEnd: int, text: string, inClip: bool, clipId: int|null, clipStart: int|null, clipEnd: int|null}|array{type: 'gap', label: string, prevSegmentIndex: int, nextSegmentIndex: int, inClip: bool, clipId: int|null, clipStart: int|null, clipEnd: int|null}>
     */
    #[Computed]
    public function transcriptRows(): array
    {
        $segments = $this->getRecord()->transcript['segments'] ?? [];
        $rows = [];
        $segmentIndices = [];
        $previousEnd = null;

        // Load clip ranges for this sermon video
        $clips = $this->getRecord()->sermonClips()
            ->orderBy('start_segment_index')
            ->get(['id', 'start_segment_index', 'end_segment_index']);

        foreach ($segments as $index => $segment) {
            if ($previousEnd !== null) {
                $gap = $segment['start'] - $previousEnd;
                if ($gap > $this->gapThreshold) {
                    $gapClip = $clips->first(fn ($clip) => ($index - 1) >= $clip->start_segment_index && $index <= $clip->end_segment_index);

                    $rows[] = [
                        'type' => 'gap',
                        'label' => $this->formatGap($gap),
                        'prevSegmentIndex' => $index - 1,
                        'nextSegmentIndex' => $index,
                        'inClip' => $gapClip !== null,
                        'clipId' => $gapClip?->id,
                        'clipStart' => $gapClip?->start_segment_index,
                        'clipEnd' => $gapClip?->end_segment_index,
                    ];
                }
            }

            $matchingClip = $clips->first(fn ($clip) => $index >= $clip->start_segment_index && $index <= $clip->end_segment_index);

            $rowIndex = count($rows);
            $rows[] = [
                'type' => 'segment',
                'start' => $segment['start'],
                'end' => $segment['end'],
                'segmentIndex' => $index,
                'highlightEnd' => $index,
                'text' => trim($segment['text']),
                'inClip' => $matchingClip !== null,
                'clipId' => $matchingClip?->id,
                'clipStart' => $matchingClip?->start_segment_index,
                'clipEnd' => $matchingClip?->end_segment_index,
            ];
            $segmentIndices[] = $rowIndex;

            $previousEnd = $segment['end'];
        }

        // Walk backward to precompute highlightEnd for each segment.
        // Since earlier segments start sooner, their 60s window ends at or
        // before the next segment's window: highlightEnd[i] <= highlightEnd[i+1].
        // Starting from the previous answer and shrinking gives O(n) total.
        $segmentCount = count($segmentIndices);
        $lastHighlight = $segmentCount - 1;

        for ($i = $segmentCount - 1; $i >= 0; $i--) {
            $row = $rows[$segmentIndices[$i]];
            $anchorStart = $row['start'];

            // Start from the next segment's highlightEnd (or end of list)
            if ($i < $segmentCount - 1) {
                $lastHighlight = $rows[$segmentIndices[$i + 1]]['highlightEnd'];
            }

            // Shrink back while the window exceeds 60s
            while ($lastHighlight > $row['segmentIndex']) {
                $candidateEnd = $rows[$segmentIndices[$lastHighlight]]['end'];
                if ($candidateEnd - $anchorStart <= 60) {
                    break;
                }
                $lastHighlight--;
            }

            // Shrink back past any trailing clip segments so the
            // highlight never ends right before a gap into a clip
            while ($lastHighlight > $row['segmentIndex'] && $rows[$segmentIndices[$lastHighlight]]['inClip']) {
                $lastHighlight--;
            }

            $rows[$segmentIndices[$i]]['highlightEnd'] = $lastHighlight;
        }

        return $rows;
    }

    /**
     * @return array<int, array{start: float, end: float}>
     */
    #[Computed]
    public function segmentTimings(): array
    {
        $segments = $this->getRecord()->transcript['segments'] ?? [];
        $timings = [];

        foreach ($segments as $index => $segment) {
            $timings[$index] = [
                'start' => $segment['start'],
                'end' => $segment['end'],
            ];
        }

        return $timings;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function clipDurations(): array
    {
        $segments = $this->getRecord()->transcript['segments'] ?? [];
        $clips = $this->getRecord()->sermonClips()
            ->orderBy('start_segment_index')
            ->get(['id', 'start_segment_index', 'end_segment_index']);

        $durations = [];

        foreach ($clips as $clip) {
            $startTime = $segments[$clip->start_segment_index]['start'] ?? 0;
            $endTime = $segments[$clip->end_segment_index]['end'] ?? 0;
            $durations[$clip->id] = $this->formatDuration($endTime - $startTime);
        }

        return $durations;
    }

    public function formatDuration(float $seconds): string
    {
        $totalSeconds = (int) round($seconds);
        $minutes = intdiv($totalSeconds, 60);
        $secs = $totalSeconds % 60;

        return sprintf('%d:%02d', $minutes, $secs);
    }

    public function resizeClip(int $clipId, int $newStart, int $newEnd): void
    {
        $clip = $this->getRecord()->sermonClips()->findOrFail($clipId);
        $segments = $this->getRecord()->transcript['segments'] ?? [];

        if ($newStart > $newEnd) {
            [$newStart, $newEnd] = [$newEnd, $newStart];
        }

        if (! isset($segments[$newStart]) || ! isset($segments[$newEnd])) {
            Log::error('resizeClip: segment index out of range', [
                'clip_id' => $clipId,
                'newStart' => $newStart,
                'newEnd' => $newEnd,
            ]);

            return;
        }

        $duration = $segments[$newEnd]['end'] - $segments[$newStart]['start'];
        if ($duration > 90) {
            Log::warning('resizeClip: duration exceeds 90s', [
                'clip_id' => $clipId,
                'duration' => $duration,
            ]);

            return;
        }

        $overlapping = $this->getRecord()->sermonClips()
            ->where('id', '!=', $clipId)
            ->where('start_segment_index', '<=', $newEnd)
            ->where('end_segment_index', '>=', $newStart)
            ->exists();

        if ($overlapping) {
            Log::warning('resizeClip: would overlap with another clip', [
                'clip_id' => $clipId,
                'newStart' => $newStart,
                'newEnd' => $newEnd,
            ]);

            return;
        }

        $clip->update([
            'start_segment_index' => $newStart,
            'end_segment_index' => $newEnd,
        ]);

        unset($this->transcriptRows, $this->clipDurations);
    }

    public function createClip(int $startSegmentIndex, int $endSegmentIndex): void
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

            return;
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

        unset($this->transcriptRows, $this->clipDurations);
    }

    #[Computed]
    public function lastSegmentStart(): float
    {
        $segments = $this->getRecord()->transcript['segments'] ?? [];
        $last = end($segments);

        return $last !== false ? $last['start'] : 0.0;
    }

    public function formatTimestamp(float $seconds): string
    {
        $totalSeconds = (int) $seconds;
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $secs = $totalSeconds % 60;

        if ($this->lastSegmentStart >= 3600) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    public function formatGap(float $seconds): string
    {
        $totalSeconds = (int) round($seconds);

        if ($totalSeconds >= 60) {
            $minutes = intdiv($totalSeconds, 60);
            $secs = $totalSeconds % 60;

            return sprintf('%dm %02ds pause', $minutes, $secs);
        }

        return sprintf('%ds pause', $totalSeconds);
    }
}
