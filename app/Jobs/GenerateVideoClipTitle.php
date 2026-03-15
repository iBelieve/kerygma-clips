<?php

namespace App\Jobs;

use App\Ai\Agents\VideoClipTitleGenerator;
use App\Models\VideoClip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateVideoClipTitle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public VideoClip $videoClip
    ) {}

    public function handle(): void
    {
        if ($this->videoClip->title_manually_edited) {
            return;
        }

        $clipText = $this->videoClip->getTranscriptText();

        if ($clipText === '') {
            Log::warning('GenerateVideoClipTitle: no transcript text found for clip', [
                'clip_id' => $this->videoClip->id,
            ]);

            return;
        }

        $prompt = $clipText;

        $videoTitle = $this->videoClip->video->title;

        if ($videoTitle !== null) {
            $prompt = "Sermon: {$videoTitle}\n\nTranscript:\n{$clipText}";
        }

        $response = (new VideoClipTitleGenerator)->prompt($prompt);

        $this->videoClip->update([
            'title' => trim((string) $response),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateVideoClipTitle failed', [
            'clip_id' => $this->videoClip->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
