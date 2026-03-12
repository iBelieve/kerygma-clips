<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\VideoClip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class GenerateClipThumbnail implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public VideoClip $videoClip
    ) {
        $this->onQueue('video-processing');
    }

    public function uniqueId(): int
    {
        return $this->videoClip->id;
    }

    public function handle(): void
    {
        $clip = $this->videoClip;
        $video = $clip->video;

        try {
            $clip->update([
                'thumbnail_status' => JobStatus::Processing,
                'thumbnail_error' => null,
            ]);

            if ($video->vertical_video_status !== JobStatus::Completed || $video->vertical_video_path === null) {
                throw new \RuntimeException('Video does not have a completed vertical video');
            }

            $clip->refresh();

            $inputDisk = Storage::disk('public');
            $inputPath = $inputDisk->path($video->vertical_video_path);

            // Seek to the middle of the clip for the key frame
            $clipMidpoint = $clip->starts_at + (($clip->ends_at - $clip->starts_at) / 2);

            // Build output path
            $videoDate = $video->date->timezone('America/Chicago');
            $clipStartSeconds = (int) floor($clip->starts_at);
            $outputRelativePath = sprintf(
                'thumbnails/%s_%02d%02d.jpg',
                $videoDate->format('Y-m-d_Hi'),
                intdiv($clipStartSeconds, 60),
                $clipStartSeconds % 60,
            );

            // Delete old thumbnail if path changed
            $oldPath = $clip->thumbnail_path;
            if ($oldPath !== null && $oldPath !== $outputRelativePath) {
                Storage::disk('public')->delete($oldPath);
            }

            $outputDisk = Storage::disk('public');
            $outputAbsolutePath = $outputDisk->path($outputRelativePath);

            $outputDir = dirname($outputAbsolutePath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Step 1: Extract the key frame (no captions since we use the vertical video source)
            $framePath = tempnam(sys_get_temp_dir(), 'thumb_frame_').'.png';

            $extractResult = Process::timeout(30)->run([
                'ffmpeg',
                '-ss', (string) $clipMidpoint,
                '-i', $inputPath,
                '-frames:v', '1',
                '-q:v', '1',
                '-y', $framePath,
            ]);

            if ($extractResult->failed()) {
                throw new \RuntimeException('Frame extraction failed: '.($extractResult->errorOutput() ?: $extractResult->output()));
            }

            // Step 2: Apply vignette + title overlay using ffmpeg filters
            $title = $clip->title ?? '';
            $fontsDir = resource_path('fonts');
            $filters = $this->buildFilterGraph($title, $fontsDir);

            $compositeResult = Process::timeout(60)->run([
                'ffmpeg',
                '-i', $framePath,
                '-vf', $filters,
                '-q:v', '2',
                '-y', $outputAbsolutePath,
            ]);

            if ($compositeResult->failed()) {
                throw new \RuntimeException('Thumbnail compositing failed: '.($compositeResult->errorOutput() ?: $compositeResult->output()));
            }

            $clip->update([
                'thumbnail_status' => JobStatus::Completed,
                'thumbnail_path' => $outputRelativePath,
            ]);
        } catch (\Throwable $e) {
            Log::error('Clip thumbnail generation failed', [
                'video_clip_id' => $clip->id,
                'video_id' => $video->id,
                'exception' => $e,
            ]);

            $clip->updateQuietly([
                'thumbnail_status' => JobStatus::Failed,
                'thumbnail_error' => $e->getMessage(),
            ]);
        } finally {
            if (isset($framePath) && file_exists($framePath)) {
                unlink($framePath);
            }
        }
    }

    /**
     * Build the ffmpeg filter graph for vignette darkening and title text overlay.
     */
    private function buildFilterGraph(string $title, string $fontsDir): string
    {
        // Vignette to dim edges and focus on center/person
        // The angle parameter controls how aggressive the darkening is
        $filters = 'vignette=angle=PI/3';

        // Add a subtle dark gradient overlay from bottom for text readability
        // Draw a semi-transparent dark rectangle at the bottom third
        $filters .= ",drawbox=x=0:y=ih*0.6:w=iw:h=ih*0.4:color=black@0.4:t=fill";

        if ($title !== '') {
            $escapedTitle = $this->escapeDrawtext($title);
            $fontFile = $fontsDir.'/Montserrat-Bold.ttf';

            // Large italic bold title text with shadow, positioned in lower third
            // Using drawtext with a shadow for depth
            $filters .= sprintf(
                ",drawtext=fontfile='%s':text='%s'"
                .":fontsize=72:fontcolor=white"
                .":x=(w-text_w)/2:y=h*0.72"
                .":shadowcolor=black@0.7:shadowx=3:shadowy=3"
                // Fake italic using a combination approach — tilt not available in drawtext
                // so we'll do it with a second slightly offset line for a glow effect
                ,
                $fontFile,
                $escapedTitle,
            );

            // Add a second text layer slightly offset for a glow/emphasis effect
            $filters .= sprintf(
                ",drawtext=fontfile='%s':text='%s'"
                .":fontsize=72:fontcolor=white@0.3"
                .":x=(w-text_w)/2+1:y=h*0.72+1"
                ,
                $fontFile,
                $escapedTitle,
            );
        }

        return $filters;
    }

    /**
     * Escape text for ffmpeg drawtext filter.
     */
    private function escapeDrawtext(string $text): string
    {
        // ffmpeg drawtext requires escaping these characters
        $text = str_replace('\\', '\\\\\\\\', $text);
        $text = str_replace("'", "\u{2019}", $text); // Replace apostrophe with typographic quote
        $text = str_replace(':', '\\:', $text);
        $text = str_replace('%', '%%', $text);

        // Wrap long titles — insert newline after ~25 chars at a word boundary
        if (mb_strlen($text) > 25) {
            $text = wordwrap($text, 25, "\n", false);
            // Only keep first 3 lines max
            $lines = explode("\n", $text);
            $text = implode("\n", array_slice($lines, 0, 3));
        }

        return $text;
    }
}
