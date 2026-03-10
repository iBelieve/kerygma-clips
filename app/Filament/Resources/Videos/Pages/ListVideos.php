<?php

namespace App\Filament\Resources\Videos\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\Videos\VideoResource;
use App\Jobs\ConvertToVerticalVideo;
use App\Jobs\ExtractPreviewFrame;
use App\Jobs\ScanSermonVideos;
use App\Jobs\TranscribeVideo;
use App\Models\Video;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListVideos extends ListRecords
{
    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('Scan for Videos')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    ScanSermonVideos::dispatch(verbose: true, includeRecent: true);

                    Notification::make()
                        ->title('Scan started')
                        ->body('A background scan for new sermon videos has been dispatched.')
                        ->success()
                        ->send();
                }),

            ActionGroup::make([
                ActionGroup::make([
                    Action::make('transcribe')
                        ->label('Re-transcribe All')
                        ->icon('heroicon-o-language')
                        ->requiresConfirmation()
                        ->action(function () {
                            $videos = Video::all();

                            foreach ($videos as $video) {
                                TranscribeVideo::dispatch($video);
                            }

                            Notification::make()
                                ->title('Transcription queued')
                                ->body("Dispatched transcription for {$videos->count()} video(s).")
                                ->success()
                                ->send();
                        }),

                    Action::make('convert_to_vertical')
                        ->label('Re-convert All to Vertical')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->requiresConfirmation()
                        ->action(function () {
                            $videos = Video::all();

                            foreach ($videos as $video) {
                                ConvertToVerticalVideo::dispatch($video);
                            }

                            Notification::make()
                                ->title('Vertical conversion queued')
                                ->body("Dispatched vertical conversion for {$videos->count()} video(s).")
                                ->success()
                                ->send();
                        }),

                    Action::make('extract_frames')
                        ->label('Re-extract All Frames')
                        ->icon('heroicon-o-camera')
                        ->requiresConfirmation()
                        ->action(function () {
                            $videos = Video::all();

                            foreach ($videos as $video) {
                                ExtractPreviewFrame::dispatch($video);
                            }

                            Notification::make()
                                ->title('Frame extraction queued')
                                ->body("Dispatched frame extraction for {$videos->count()} video(s).")
                                ->success()
                                ->send();
                        }),
                ])->dropdown(false),

                ActionGroup::make([
                    Action::make('transcribe_missing')
                        ->label('Transcribe Missing')
                        ->icon('heroicon-o-language')
                        ->requiresConfirmation()
                        ->visible(fn () => Video::where('transcript_status', '!=', JobStatus::Completed)->exists())
                        ->action(function () {
                            $videos = Video::where('transcript_status', '!=', JobStatus::Completed)
                                ->get();

                            foreach ($videos as $video) {
                                TranscribeVideo::dispatch($video);
                            }

                            Notification::make()
                                ->title('Transcription queued')
                                ->body("Dispatched transcription for {$videos->count()} video(s).")
                                ->success()
                                ->send();
                        }),

                    Action::make('convert_missing_to_vertical')
                        ->label('Convert Missing to Vertical')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->requiresConfirmation()
                        ->visible(fn () => Video::where('vertical_video_status', '!=', JobStatus::Completed)->exists())
                        ->action(function () {
                            $videos = Video::where('vertical_video_status', '!=', JobStatus::Completed)
                                ->get();

                            foreach ($videos as $video) {
                                ConvertToVerticalVideo::dispatch($video);
                            }

                            Notification::make()
                                ->title('Vertical conversion queued')
                                ->body("Dispatched vertical conversion for {$videos->count()} video(s).")
                                ->success()
                                ->send();
                        }),

                    Action::make('extract_missing_frames')
                        ->label('Extract Missing Frames')
                        ->icon('heroicon-o-camera')
                        ->requiresConfirmation()
                        ->visible(fn () => Video::whereNull('preview_frame_path')->exists())
                        ->action(function () {
                            $videos = Video::whereNull('preview_frame_path')
                                ->get();

                            foreach ($videos as $video) {
                                ExtractPreviewFrame::dispatch($video);
                            }

                            Notification::make()
                                ->title('Frame extraction queued')
                                ->body("Dispatched frame extraction for {$videos->count()} video(s).")
                                ->success()
                                ->send();
                        }),
                ])->dropdown(false),
            ])
                ->icon('heroicon-o-cog-6-tooth')
                ->label('')
                ->color('gray')
                ->button()
                ->dropdownWidth(Width::ExtraSmall)
                ->dropdownOffset(16),
        ];
    }
}
