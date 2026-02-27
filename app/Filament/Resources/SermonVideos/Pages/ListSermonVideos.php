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
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSermonVideos extends ListRecords
{
    protected static string $resource = SermonVideoResource::class;

    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        return [
            'echo-private:sermon-updates,SermonVideoUpdated' => '$refresh',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('Scan for Videos')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    ScanSermonVideos::dispatch(verbose: true, includeRecent: true);

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
                ->visible(fn (): bool => SermonVideo::where('transcript_status', '!=', JobStatus::Completed)->exists())
                ->requiresConfirmation()
                ->action(function () {
                    $videos = SermonVideo::where('transcript_status', '!=', JobStatus::Completed)->get();

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
                ->label('Convert All to Vertical')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('primary')
                ->visible(fn (): bool => SermonVideo::where('vertical_video_status', '!=', JobStatus::Completed)->exists())
                ->requiresConfirmation()
                ->action(function () {
                    $videos = SermonVideo::where('vertical_video_status', '!=', JobStatus::Completed)->get();

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
                ->label('Extract All Frames')
                ->icon('heroicon-o-camera')
                ->color('primary')
                ->visible(fn (): bool => SermonVideo::whereNull('preview_frame_path')->exists())
                ->requiresConfirmation()
                ->action(function () {
                    $videos = SermonVideo::whereNull('preview_frame_path')->get();

                    foreach ($videos as $video) {
                        ExtractPreviewFrame::dispatch($video);
                    }

                    Notification::make()
                        ->title('Frame extraction queued')
                        ->body("Dispatched frame extraction for {$videos->count()} sermon video(s).")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function updateVideoFraming(int $videoId, int $cropCenter): void
    {
        $video = SermonVideo::findOrFail($videoId);
        $video->update(['vertical_video_crop_center' => $cropCenter]);
        ConvertToVerticalVideo::dispatch($video);

        Notification::make()
            ->title('Framing updated')
            ->body('Vertical video conversion has been re-queued.')
            ->success()
            ->send();

        $this->unmountAction();
    }
}
