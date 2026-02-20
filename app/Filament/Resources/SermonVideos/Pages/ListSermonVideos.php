<?php

namespace App\Filament\Resources\SermonVideos\Pages;

use App\Filament\Resources\SermonVideos\SermonVideoResource;
use App\Jobs\ScanSermonVideos;
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
                ->requiresConfirmation()
                ->modalHeading('Scan for New Videos')
                ->modalDescription('This will scan the sermon videos folder for new video files. The scan runs in the background.')
                ->modalSubmitActionLabel('Start Scan')
                ->action(function () {
                    ScanSermonVideos::dispatch(verbose: true);

                    Notification::make()
                        ->title('Scan started')
                        ->body('A background scan for new sermon videos has been dispatched.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
