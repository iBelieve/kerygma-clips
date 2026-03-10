<?php

namespace App\Filament\Resources\Videos\Pages;

use App\Enums\VideoType;
use App\Filament\Resources\Videos\VideoResource;
use App\Jobs\ConvertToVerticalVideo;
use App\Jobs\ExtractPreviewFrame;
use App\Jobs\TranscribeVideo;
use App\Models\Video;
use App\Services\VideoProbe;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

class UploadVideo extends CreateRecord
{
    protected static string $resource = VideoResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Upload Video';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = VideoType::Upload;
        $data['date'] = now();

        $disk = Storage::disk('local');
        $absolutePath = $disk->path($data['raw_video_path']);
        $videoProbe = app(VideoProbe::class);
        $data['duration'] = $videoProbe->getDurationInSeconds($absolutePath);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Video $record */
        $record = $this->record;

        TranscribeVideo::dispatch($record);
        ConvertToVerticalVideo::dispatch($record);
        ExtractPreviewFrame::dispatch($record);
    }
}
