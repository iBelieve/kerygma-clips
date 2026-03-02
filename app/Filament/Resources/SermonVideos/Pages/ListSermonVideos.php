<?php

namespace App\Filament\Resources\SermonVideos\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\SermonVideos\SermonVideoResource;
use App\Jobs\ConvertToVerticalVideo;
use App\Jobs\ExtractPreviewFrame;
use App\Jobs\ScanSermonVideos;
use App\Jobs\TranscribeSermonVideo;
use App\Models\SermonVideo;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListSermonVideos extends ListRecords
{
    protected static string $resource = SermonVideoResource::class;

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
                            $videos = SermonVideo::all();

                            foreach ($videos as $video) {
                                TranscribeSermonVideo::dispatch($video);
                            }

                            Notification::make()
                                ->title('Transcription queued')
                                ->body("Dispatched transcription for {$videos->count()} sermon video(s).")
                                ->success()
                                ->send();
                        }),

                    Action::make('convert_to_vertical')
                        ->label('Re-convert All to Vertical')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->requiresConfirmation()
                        ->action(function () {
                            $videos = SermonVideo::all();

                            foreach ($videos as $video) {
                                ConvertToVerticalVideo::dispatch($video);
                            }

                            Notification::make()
                                ->title('Vertical conversion queued')
                                ->body("Dispatched vertical conversion for {$videos->count()} sermon video(s).")
                                ->success()
                                ->send();
                        }),

                    Action::make('extract_frames')
                        ->label('Re-extract All Frames')
                        ->icon('heroicon-o-camera')
                        ->requiresConfirmation()
                        ->action(function () {
                            $videos = SermonVideo::all();

                            foreach ($videos as $video) {
                                ExtractPreviewFrame::dispatch($video);
                            }

                            Notification::make()
                                ->title('Frame extraction queued')
                                ->body("Dispatched frame extraction for {$videos->count()} sermon video(s).")
                                ->success()
                                ->send();
                        }),
                ])->dropdown(false),

                ActionGroup::make([
                    Action::make('transcribe_missing')
                        ->label('Transcribe Missing')
                        ->icon('heroicon-o-language')
                        ->requiresConfirmation()
                        ->visible(fn () => SermonVideo::where('transcript_status', '!=', JobStatus::Completed)->exists())
                        ->action(function () {
                            $videos = SermonVideo::where('transcript_status', '!=', JobStatus::Completed)
                                ->get();

                            foreach ($videos as $video) {
                                TranscribeSermonVideo::dispatch($video);
                            }

                            Notification::make()
                                ->title('Transcription queued')
                                ->body("Dispatched transcription for {$videos->count()} sermon video(s).")
                                ->success()
                                ->send();
                        }),

                    Action::make('convert_missing_to_vertical')
                        ->label('Convert Missing to Vertical')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->requiresConfirmation()
                        ->visible(fn () => SermonVideo::where('vertical_video_status', '!=', JobStatus::Completed)->exists())
                        ->action(function () {
                            $videos = SermonVideo::where('vertical_video_status', '!=', JobStatus::Completed)
                                ->get();

                            foreach ($videos as $video) {
                                ConvertToVerticalVideo::dispatch($video);
                            }

                            Notification::make()
                                ->title('Vertical conversion queued')
                                ->body("Dispatched vertical conversion for {$videos->count()} sermon video(s).")
                                ->success()
                                ->send();
                        }),

                    Action::make('extract_missing_frames')
                        ->label('Extract Missing Frames')
                        ->icon('heroicon-o-camera')
                        ->requiresConfirmation()
                        ->visible(fn () => SermonVideo::whereNull('preview_frame_path')->exists())
                        ->action(function () {
                            $videos = SermonVideo::whereNull('preview_frame_path')
                                ->get();

                            foreach ($videos as $video) {
                                ExtractPreviewFrame::dispatch($video);
                            }

                            Notification::make()
                                ->title('Frame extraction queued')
                                ->body("Dispatched frame extraction for {$videos->count()} sermon video(s).")
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
