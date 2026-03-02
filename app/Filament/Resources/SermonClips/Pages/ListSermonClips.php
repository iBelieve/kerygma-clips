<?php

namespace App\Filament\Resources\SermonClips\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\SermonClips\SermonClipResource;
use App\Jobs\ExtractSermonClipVerticalVideo;
use App\Models\SermonClip;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSermonClips extends ListRecords
{
    protected static string $resource = SermonClipResource::class;

    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        return [
            'echo-private:sermon-updates,SermonClipUpdated' => '$refresh',
            'echo-private:sermon-updates,SermonVideoUpdated' => '$refresh',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('extract_all_videos')
                    ->label('Re-extract All Videos')
                    ->icon('heroicon-o-film')
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
                Action::make('extract_missing_videos')
                    ->label('Extract Missing Videos')
                    ->icon('heroicon-o-film')
                    ->color('primary')
                    ->visible(fn () => SermonClip::where('clip_video_status', '!=', JobStatus::Completed)->exists())
                    ->action(function () {
                        $clips = SermonClip::where('clip_video_status', '!=', JobStatus::Completed)
                            ->get();

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
                ->label('')
                ->color('')
                ->button(),
        ];
    }
}
