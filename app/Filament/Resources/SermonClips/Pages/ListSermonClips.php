<?php

namespace App\Filament\Resources\SermonClips\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\SermonClips\SermonClipResource;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Models\SermonClip;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSermonClips extends ListRecords
{
    protected static string $resource = SermonClipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('extract_all_videos')
                ->label('Regenerate All Videos')
                ->icon('heroicon-o-film')
                ->color('warning')
                ->visible(fn (): bool => SermonClip::exists())
                ->requiresConfirmation()
                ->action(function () {
                    $clips = SermonClip::all();

                    foreach ($clips as $clip) {
                        ExtractSermonClipVerticalVideo::dispatch($clip);
                    }

                    Notification::make()
                        ->title('Clip extraction queued')
                        ->body("Dispatched video extraction for {$clips->count()} clip(s).")
                        ->success()
                        ->send();
                }),
        ];
    }
}
