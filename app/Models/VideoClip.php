<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\VideoType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VideoClip extends Model
{
    use HasFactory;

    public const int MAX_CLIP_DURATION = 180;

    public const float MAX_PAUSE_BEFORE = 0.25;

    public const float MAX_PAUSE_AFTER = 0.5;

    protected $fillable = [
        'video_id',
        'start_segment_index',
        'end_segment_index',
        'starts_at',
        'ends_at',
        'pause_before',
        'pause_after',
        'title',
        'excerpt',
        'clip_video_status',
        'clip_video_path',
        'clip_video_error',
        'clip_video_started_at',
        'clip_video_completed_at',
        'thumbnail_status',
        'thumbnail_path',
        'thumbnail_error',
    ];

    protected $casts = [
        'start_segment_index' => 'integer',
        'end_segment_index' => 'integer',
        'starts_at' => 'float',
        'ends_at' => 'float',
        'pause_before' => 'float',
        'pause_after' => 'float',
        'duration' => 'float',
        'clip_video_status' => JobStatus::class,
        'clip_video_started_at' => 'immutable_datetime',
        'clip_video_completed_at' => 'immutable_datetime',
        'clip_video_duration' => 'integer',
        'thumbnail_status' => JobStatus::class,
    ];

    /**
     * Calculate pause timing for a clip based on segment gaps.
     *
     * Returns pause_before, pause_after, starts_at, and ends_at values
     * derived from the gaps between transcript segments.
     *
     * @param  list<array{start: float, end: float, text: string}>  $segments
     * @return array{pause_before: float, pause_after: float, starts_at: float, ends_at: float}
     */
    public static function calculatePauseTiming(
        int $startSegmentIndex,
        int $endSegmentIndex,
        array $segments,
        float $videoDuration,
    ): array {
        if (! isset($segments[$startSegmentIndex], $segments[$endSegmentIndex])) {
            throw new \RuntimeException("Segment indices [{$startSegmentIndex}, {$endSegmentIndex}] are out of bounds.");
        }

        $segmentStart = (float) $segments[$startSegmentIndex]['start'];
        $segmentEnd = (float) $segments[$endSegmentIndex]['end'];

        // Calculate pause_before: half the gap to the preceding segment, max 0.25s
        if ($startSegmentIndex > 0) {
            $prevEnd = (float) $segments[$startSegmentIndex - 1]['end'];
            $gapBefore = max(0, $segmentStart - $prevEnd);
        } else {
            $gapBefore = max(0, $segmentStart);
        }
        $pauseBefore = min($gapBefore / 2, self::MAX_PAUSE_BEFORE);

        // Calculate pause_after: half the gap to the following segment, max 0.5s
        if (isset($segments[$endSegmentIndex + 1])) {
            $nextStart = (float) $segments[$endSegmentIndex + 1]['start'];
            $gapAfter = max(0, $nextStart - $segmentEnd);
        } else {
            $gapAfter = max(0, $videoDuration - $segmentEnd);
        }
        $pauseAfter = min($gapAfter / 2, self::MAX_PAUSE_AFTER);

        return [
            'pause_before' => $pauseBefore,
            'pause_after' => $pauseAfter,
            'starts_at' => $segmentStart - $pauseBefore,
            'ends_at' => $segmentEnd + $pauseAfter,
        ];
    }

    public function buildDescription(string $excerpt): string
    {
        $video = $this->video;
        $subtitle = $video->getMidsentenceSubtitle();
        $scripture = $video->scripture;
        $preacher = $video->preacher;
        $callToAction = Settings::instance()->call_to_action;

        $lines = [$excerpt, ''];

        $isSermon = $video->type === VideoType::Sermon;
        $sermonLine = $isSermon ? 'Clip from a sermon' : 'Clip from a video';
        $hasDetail = $scripture || $subtitle || $preacher;

        if ($scripture) {
            $sermonLine .= " on {$scripture}";
        }
        if ($subtitle) {
            $sermonLine .= " for {$subtitle}";
        }
        if ($preacher) {
            $sermonLine .= " by {$preacher}";
        }
        $sermonLine .= '.';

        if ($hasDetail) {
            $lines[] = $sermonLine;
        }

        if ($callToAction) {
            $lines[] = '';
            $lines[] = $callToAction;
        }

        return implode("\n", $lines);
    }

    public function getTranscriptText(): string
    {
        $segments = $this->video->transcript['segments'] ?? [];

        return collect($segments)
            ->slice(
                $this->start_segment_index,
                $this->end_segment_index - $this->start_segment_index + 1
            )
            ->pluck('text')
            ->map(fn (string $text): string => trim($text))
            ->filter()
            ->join(' ');
    }

    protected static function booted(): void
    {
        static::creating(function (VideoClip $clip): void {
            if ($clip->excerpt === null) {
                $clip->excerpt = $clip->getTranscriptText();
            }
        });

        static::saving(function (VideoClip $clip): void {
            $video = $clip->video;
            $segments = $video->transcript['segments'] ?? [];

            $timing = self::calculatePauseTiming(
                $clip->start_segment_index,
                $clip->end_segment_index,
                $segments,
                (float) $video->duration,
            );

            $clip->pause_before = $timing['pause_before'];
            $clip->pause_after = $timing['pause_after'];
            $clip->starts_at = $timing['starts_at'];
            $clip->ends_at = $timing['ends_at'];
        });

        static::deleting(function (VideoClip $clip): void {
            if ($clip->clip_video_path) {
                Storage::disk('public')->delete($clip->clip_video_path);
            }

            if ($clip->thumbnail_path) {
                Storage::disk('public')->delete($clip->thumbnail_path);
            }
        });
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
