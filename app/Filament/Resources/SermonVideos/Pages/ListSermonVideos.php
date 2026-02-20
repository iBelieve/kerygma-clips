<?php

namespace App\Filament\Resources\SermonVideos\Pages;

use App\Enums\TranscriptStatus;
use App\Filament\Resources\SermonVideos\SermonVideoResource;
use App\Jobs\ScanSermonVideos;
use App\Jobs\TranscribeSermonVideo;
use App\Models\SermonVideo;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSermonVideos extends ListRecords
{
    protected static string $resource = SermonVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('Scan for Videos')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    ScanSermonVideos::dispatch(verbose: true);

                    Notification::make()
                        ->title('Scan started')
                        ->body('A background scan for new sermon videos has been dispatched.')
                        ->success()
                        ->send();
                }),

            Action::make('transcribe')
                ->label('Transcribe All')
                ->icon('heroicon-o-language')
                ->color('primary')
                ->visible(fn (): bool => SermonVideo::where('transcript_status', '!=', TranscriptStatus::Completed)->exists())
                ->requiresConfirmation()
                ->action(function () {
                    $videos = SermonVideo::where('transcript_status', '!=', TranscriptStatus::Completed)->get();

                    foreach ($videos as $video) {
                        TranscribeSermonVideo::dispatch($video);
                    }

                    Notification::make()
                        ->title('Transcription queued')
                        ->body("Dispatched transcription for {$videos->count()} sermon video(s).")
                        ->success()
                        ->send();
                }),
        ];
    }
}
