<?php

namespace App\Services;

use App\Models\VideoClip;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ThumbnailGenerator
{
    /**
     * Generate a thumbnail for a video clip.
     *
     * Extracts a key frame from the vertical video (before captions),
     * applies smart background dimming using person segmentation,
     * and overlays the clip title as styled text.
     *
     * @return string The relative path to the generated thumbnail on the public disk.
     */
    public function generate(VideoClip $clip): string
    {
        $video = $clip->video;
        $publicDisk = Storage::disk('public');
        $verticalVideoPath = $publicDisk->path($video->vertical_video_path);

        // Seek to ~33% into the clip to avoid opening transitions
        $seekTime = $clip->starts_at + ($clip->duration * 0.33);

        $tempFrame = tempnam(sys_get_temp_dir(), 'thumb_frame_').'.jpg';
        $tempMask = tempnam(sys_get_temp_dir(), 'thumb_mask_').'.png';
        $tempDimmed = tempnam(sys_get_temp_dir(), 'thumb_dimmed_').'.jpg';

        try {
            $this->extractFrame($verticalVideoPath, $seekTime, $tempFrame);
            $this->generatePersonMask($tempFrame, $tempMask);
            $this->applyBackgroundDimming($tempFrame, $tempMask, $tempDimmed);

            $outputRelativePath = $this->buildOutputPath($clip);
            $outputAbsolutePath = $publicDisk->path($outputRelativePath);

            $outputDir = dirname($outputAbsolutePath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $this->overlayTitle($tempDimmed, $clip->title, $outputAbsolutePath);

            return $outputRelativePath;
        } finally {
            @unlink($tempFrame);
            @unlink($tempMask);
            @unlink($tempDimmed);
        }
    }

    /**
     * Build the output path for a thumbnail, matching the clip naming convention.
     */
    public function buildOutputPath(VideoClip $clip): string
    {
        $videoDate = $clip->video->date->timezone('America/Chicago');
        $clipStartSeconds = (int) floor($clip->starts_at);

        return sprintf(
            'thumbnails/%s_%02d%02d.jpg',
            $videoDate->format('Y-m-d_Hi'),
            intdiv($clipStartSeconds, 60),
            $clipStartSeconds % 60,
        );
    }

    private function extractFrame(string $videoPath, float $seekTime, string $outputPath): void
    {
        $result = Process::timeout(30)->run([
            'ffmpeg',
            '-ss', (string) $seekTime,
            '-i', $videoPath,
            '-frames:v', '1',
            '-q:v', '2',
            '-y', $outputPath,
        ]);

        if ($result->failed()) {
            throw new \RuntimeException('Frame extraction failed: '.($result->errorOutput() ?: $result->output()));
        }
    }

    private function generatePersonMask(string $framePath, string $maskPath): void
    {
        $scriptPath = base_path('scripts/segment_person.py');

        $result = Process::path(base_path())
            ->timeout(60)
            ->run([
                'python', $scriptPath,
                '--input', $framePath,
                '--output', $maskPath,
            ]);

        if ($result->failed()) {
            throw new \RuntimeException('Person segmentation failed: '.($result->errorOutput() ?: $result->output()));
        }
    }

    private function applyBackgroundDimming(string $framePath, string $maskPath, string $outputPath): void
    {
        // Use the person mask to selectively dim the background:
        // 1. Create a darkened version of the frame (brightness reduced to ~50%)
        // 2. Use maskedmerge to blend: where mask is white (person) use original, where black (bg) use darkened
        // 3. Add a soft vignette on top for extra edge focus
        $filterComplex = implode(';', [
            '[0:v]colorlevels=rimax=0.5:gimax=0.5:bimax=0.5[dark]',
            '[dark][0:v][1:v]maskedmerge[merged]',
            '[merged]vignette=PI/3[out]',
        ]);

        $result = Process::timeout(30)->run([
            'ffmpeg',
            '-i', $framePath,
            '-i', $maskPath,
            '-filter_complex', $filterComplex,
            '-map', '[out]',
            '-q:v', '2',
            '-y', $outputPath,
        ]);

        if ($result->failed()) {
            throw new \RuntimeException('Background dimming failed: '.($result->errorOutput() ?: $result->output()));
        }
    }

    private function overlayTitle(string $imagePath, string $title, string $outputPath): void
    {
        $fontPath = resource_path('fonts/Montserrat-Bold.ttf');

        $image = new \Imagick($imagePath);
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        $margin = 80;
        $maxTextWidth = $width - ($margin * 2);

        // Start with a large font size and scale down if needed
        $fontSize = 90;
        $minFontSize = 50;

        $draw = new \ImagickDraw;
        $draw->setFont($fontPath);
        $draw->setFontSize($fontSize);
        $draw->setGravity(\Imagick::GRAVITY_CENTER);

        // Scale font size down until the title fits within the max width
        while ($fontSize > $minFontSize) {
            $draw->setFontSize($fontSize);
            $metrics = $image->queryFontMetrics($draw, $title, true);

            if ($metrics['textWidth'] <= $maxTextWidth) {
                break;
            }

            $fontSize -= 4;
        }

        // Position text in the lower third of the image
        $textY = (int) ($height * 0.78);

        // Draw shadow first (offset by 4px)
        $shadow = new \ImagickDraw;
        $shadow->setFont($fontPath);
        $shadow->setFontSize($fontSize);
        $shadow->setFillColor(new \ImagickPixel('rgba(0, 0, 0, 0.7)'));
        $shadow->setTextAlignment(\Imagick::ALIGN_CENTER);
        $image->annotateImage($shadow, ($width / 2) + 4, $textY + 4, -12, $title);

        // Draw black stroke/outline
        $stroke = new \ImagickDraw;
        $stroke->setFont($fontPath);
        $stroke->setFontSize($fontSize);
        $stroke->setFillColor(new \ImagickPixel('transparent'));
        $stroke->setStrokeColor(new \ImagickPixel('black'));
        $stroke->setStrokeWidth(5);
        $stroke->setTextAlignment(\Imagick::ALIGN_CENTER);
        $image->annotateImage($stroke, $width / 2, $textY, -12, $title);

        // Draw white fill text on top
        $text = new \ImagickDraw;
        $text->setFont($fontPath);
        $text->setFontSize($fontSize);
        $text->setFillColor(new \ImagickPixel('white'));
        $text->setTextAlignment(\Imagick::ALIGN_CENTER);
        $image->annotateImage($text, $width / 2, $textY, -12, $title);

        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(92);
        $image->writeImage($outputPath);

        $image->clear();
        $image->destroy();
    }
}
