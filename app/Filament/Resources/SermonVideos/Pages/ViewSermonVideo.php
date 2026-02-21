<?php

namespace App\Filament\Resources\SermonVideos\Pages;

use App\Filament\Resources\SermonVideos\SermonVideoResource;
use App\Models\SermonVideo;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
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
     * @return list<array{type: 'segment', start: float, end: float, segmentIndex: int, highlightEnd: int, text: string, inClip: bool}|array{type: 'gap', label: string}>
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
            ->get(['start_segment_index', 'end_segment_index']);

        foreach ($segments as $index => $segment) {
            if ($previousEnd !== null) {
                $gap = $segment['start'] - $previousEnd;
                if ($gap > $this->gapThreshold) {
                    $rows[] = [
                        'type' => 'gap',
                        'label' => $this->formatGap($gap),
                    ];
                }
            }

            $inClip = $clips->contains(fn ($clip) => $index >= $clip->start_segment_index && $index <= $clip->end_segment_index);

            $rowIndex = count($rows);
            $rows[] = [
                'type' => 'segment',
                'start' => $segment['start'],
                'end' => $segment['end'],
                'segmentIndex' => $index,
                'highlightEnd' => $index,
                'text' => trim($segment['text']),
                'inClip' => $inClip,
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

            $rows[$segmentIndices[$i]]['highlightEnd'] = $lastHighlight;
        }

        return $rows;
    }

    public function createClip(int $startSegmentIndex, int $endSegmentIndex): void
    {
        // Ensure start <= end
        if ($startSegmentIndex > $endSegmentIndex) {
            [$startSegmentIndex, $endSegmentIndex] = [$endSegmentIndex, $startSegmentIndex];
        }

        $existingClips = $this->getRecord()->sermonClips()
            ->orderBy('start_segment_index')
            ->get(['start_segment_index', 'end_segment_index']);

        // Reject if start is within an existing clip
        foreach ($existingClips as $clip) {
            if ($startSegmentIndex >= $clip->start_segment_index && $startSegmentIndex <= $clip->end_segment_index) {
                return;
            }
        }

        // Truncate end if it would overlap into a following clip
        foreach ($existingClips as $clip) {
            if ($clip->start_segment_index > $startSegmentIndex && $clip->start_segment_index <= $endSegmentIndex) {
                $endSegmentIndex = $clip->start_segment_index - 1;
            }
        }

        $this->getRecord()->sermonClips()->create([
            'start_segment_index' => $startSegmentIndex,
            'end_segment_index' => $endSegmentIndex,
        ]);

        unset($this->transcriptRows);
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
