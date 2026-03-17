<?php

namespace App\Filament\Resources\VideoClips\Pages;

use App\Enums\ClipStatus;
use App\Enums\JobStatus;
use App\Filament\Resources\VideoClips\VideoClipResource;
use App\Jobs\ExtractVideoClipVerticalVideo;
use App\Models\VideoClip;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVideoClips extends ListRecords
{
    protected static string $resource = VideoClipResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make()->label('All'),
            'draft' => Tab::make()
                ->label('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ClipStatus::Draft))
                ->badge(fn () => VideoClip::where('status', ClipStatus::Draft)->count()),
            'approved' => Tab::make()
                ->label('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ClipStatus::Approved)),
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
                        $clips = VideoClip::all();

                        foreach ($clips as $clip) {
                            ExtractVideoClipVerticalVideo::dispatch($clip);
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
                    ->visible(fn () => VideoClip::where('clip_video_status', '!=', JobStatus::Completed)->exists())
                    ->action(function () {
                        $clips = VideoClip::where('clip_video_status', '!=', JobStatus::Completed)
                            ->get();

                        foreach ($clips as $clip) {
                            ExtractVideoClipVerticalVideo::dispatch($clip);
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
