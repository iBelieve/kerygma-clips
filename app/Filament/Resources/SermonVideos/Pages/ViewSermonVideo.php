<?php

namespace App\Filament\Resources\SermonVideos\Pages;

use App\Filament\Resources\SermonVideos\SermonVideoResource;
use App\Models\SermonVideo;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @extends ViewRecord<SermonVideo>
 */
class ViewSermonVideo extends ViewRecord
{
    protected static string $resource = SermonVideoResource::class;

    public function getHeading(): string|Htmlable
    {
        /** @var SermonVideo $record */
        $record = $this->getRecord();

        return $record->title
            ?? $record->date->timezone('America/Chicago')->format('M j, Y g:i A');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                ViewEntry::make('transcript')
                    ->hiddenLabel()
                    ->view('filament.resources.sermon-videos.transcript')
                    ->columnSpanFull(),
            ]);
    }
}
