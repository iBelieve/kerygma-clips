<?php

namespace App\Filament\Resources\SermonClips\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\SermonClips\SermonClipResource;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Jobs\GenerateSermonClipTitle;
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
            Action::make('generate_all_titles')
                ->label('Generate All Titles')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    $clips = SermonClip::all();

                    foreach ($clips as $clip) {
                        GenerateSermonClipTitle::dispatch($clip);
                    }

                    Notification::make()
                        ->title('Title generation queued')
                        ->body("Dispatched title generation for {$clips->count()} clip(s).")
                        ->success()
                        ->send();
                }),

            Action::make('extract_all_videos')
                ->label('Extract All Videos')
                ->icon('heroicon-o-film')
                ->color('primary')
                ->visible(fn (): bool => SermonClip::where('clip_video_status', '!=', JobStatus::Completed)->exists())
                ->requiresConfirmation()
                ->action(function () {
                    $clips = SermonClip::where('clip_video_status', '!=', JobStatus::Completed)->get();

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
