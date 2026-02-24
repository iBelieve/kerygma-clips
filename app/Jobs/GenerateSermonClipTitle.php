<?php

namespace App\Jobs;

use App\Ai\Agents\SermonClipTitleGenerator;
use App\Models\SermonClip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSermonClipTitle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public SermonClip $sermonClip
    ) {}

    public function handle(): void
    {
        $segments = $this->sermonClip->sermonVideo->transcript['segments'] ?? [];

        $clipText = collect($segments)
            ->slice(
                $this->sermonClip->start_segment_index,
                $this->sermonClip->end_segment_index - $this->sermonClip->start_segment_index + 1
            )
            ->pluck('text')
            ->map(fn (string $text): string => trim($text))
            ->filter()
            ->join(' ');

        if ($clipText === '') {
            Log::warning('GenerateSermonClipTitle: no transcript text found for clip', [
                'clip_id' => $this->sermonClip->id,
            ]);

            return;
        }

        $prompt = $clipText;

        $sermonTitle = $this->sermonClip->sermonVideo->title;

        if ($sermonTitle !== null) {
            $prompt = "Sermon: {$sermonTitle}\n\nTranscript:\n{$clipText}";
        }

        $response = (new SermonClipTitleGenerator)->prompt($prompt);

        $this->sermonClip->update([
            'ai_title' => trim((string) $response),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateSermonClipTitle failed', [
            'clip_id' => $this->sermonClip->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
