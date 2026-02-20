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
     * @return list<array{type: 'segment', start: float, text: string}|array{type: 'gap', label: string}>
     */
    #[Computed]
    public function transcriptRows(): array
    {
        $segments = $this->getRecord()->transcript['segments'] ?? [];
        $rows = [];
        $previousEnd = null;

        foreach ($segments as $segment) {
            if ($previousEnd !== null) {
                $gap = $segment['start'] - $previousEnd;
                if ($gap > $this->gapThreshold) {
                    $rows[] = [
                        'type' => 'gap',
                        'label' => $this->formatGap($gap),
                    ];
                }
            }

            $rows[] = [
                'type' => 'segment',
                'start' => $segment['start'],
                'text' => trim($segment['text']),
            ];

            $previousEnd = $segment['end'];
        }

        return $rows;
    }

    #[Computed]
    public function lastSegmentStart(): float
    {
        $segments = $this->getRecord()->transcript['segments'] ?? [];
        $last = end($segments);

        return $last !== false ? $last['start'] : 0.0;
    }

    public function formatTimestamp(float $seconds, float $lastStart): string
    {
        $totalSeconds = (int) $seconds;
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $secs = $totalSeconds % 60;

        if ($lastStart >= 3600) {
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
