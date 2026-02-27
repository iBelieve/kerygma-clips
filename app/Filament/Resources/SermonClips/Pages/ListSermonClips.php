<?php

namespace App\Filament\Resources\SermonClips\Pages;

use App\Filament\Resources\SermonClips\SermonClipResource;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Jobs\GenerateSermonClipTitle;
use App\Models\SermonClip;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSermonClips extends ListRecords
{
    protected static string $resource = SermonClipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('generate_all_titles')
                    ->label('Generate All Titles')
                    ->icon('heroicon-o-sparkles')
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
            ])
                ->icon('heroicon-o-cog-6-tooth')
                ->label('Actions'),
        ];
    }
}
