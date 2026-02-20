<?php

namespace App\Filament\Resources\SermonVideos\Pages;

use App\Filament\Resources\SermonVideos\SermonVideoResource;
use App\Models\SermonVideo;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @extends ViewRecord<SermonVideo>
 *
 * @method SermonVideo getRecord()
 */
class ViewSermonVideo extends ViewRecord
{
    protected static string $resource = SermonVideoResource::class;

    protected string $view = 'filament.resources.sermon-videos.view-sermon-video';

    public function getHeading(): string|Htmlable
    {
        return $this->getRecord()->title
            ?? $this->getRecord()->date->timezone('America/Chicago')->format('M j, Y g:i A');
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

        return sprintf('%d:%02d', $minutes, $secs);
    }
}
