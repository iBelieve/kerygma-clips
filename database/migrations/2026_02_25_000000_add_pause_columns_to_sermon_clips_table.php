<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the virtual `duration` column first because it references
        // `ends_at` which we'll be modifying during backfill.
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('duration');
        });

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->decimal('pause_before', 8, 2)->default(0)->after('ends_at');
            $table->decimal('pause_after', 8, 2)->default(0)->after('pause_before');
        });

        // Backfill existing clips: calculate pause_before/pause_after and
        // update starts_at/ends_at to include the padding.
        $clips = DB::table('sermon_clips')
            ->join('sermon_videos', 'sermon_clips.sermon_video_id', '=', 'sermon_videos.id')
            ->select(
                'sermon_clips.id',
                'sermon_clips.start_segment_index',
                'sermon_clips.end_segment_index',
                'sermon_videos.transcript',
                'sermon_videos.duration as video_duration',
            )
            ->get();

        foreach ($clips as $clip) {
            $segments = json_decode($clip->transcript, true)['segments'] ?? [];
            $startSegment = $segments[$clip->start_segment_index] ?? null;
            $endSegment = $segments[$clip->end_segment_index] ?? null;

            if (! $startSegment || ! $endSegment) {
                throw new \RuntimeException("Sermon clip {$clip->id} has segment indices that are out of bounds.");
            }

            $segmentStart = (float) $startSegment['start'];
            $segmentEnd = (float) $endSegment['end'];

            // Calculate pause_before: half the gap to the preceding segment, max 1s
            if ($clip->start_segment_index > 0) {
                $prevEnd = (float) $segments[$clip->start_segment_index - 1]['end'];
                $gapBefore = $segmentStart - $prevEnd;
            } else {
                $gapBefore = $segmentStart;
            }
            $pauseBefore = min($gapBefore / 2, 1.0);

            // Calculate pause_after: half the gap to the following segment, max 1s
            if (isset($segments[$clip->end_segment_index + 1])) {
                $nextStart = (float) $segments[$clip->end_segment_index + 1]['start'];
                $gapAfter = $nextStart - $segmentEnd;
            } else {
                $gapAfter = (float) $clip->video_duration - $segmentEnd;
            }
            $pauseAfter = min($gapAfter / 2, 1.0);

            DB::table('sermon_clips')
                ->where('id', $clip->id)
                ->update([
                    'pause_before' => $pauseBefore,
                    'pause_after' => $pauseAfter,
                    'starts_at' => $segmentStart - $pauseBefore,
                    'ends_at' => $segmentEnd + $pauseAfter,
                ]);
        }

        // Re-add the virtual duration column.
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->decimal('duration', 8, 2)->virtualAs('ends_at - starts_at')->after('pause_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the virtual `duration` column first because it references
        // `ends_at` which we'll be modifying during backfill.
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('duration');
        });

        // Revert starts_at/ends_at back to raw segment times.
        $clips = DB::table('sermon_clips')
            ->join('sermon_videos', 'sermon_clips.sermon_video_id', '=', 'sermon_videos.id')
            ->select(
                'sermon_clips.id',
                'sermon_clips.start_segment_index',
                'sermon_clips.end_segment_index',
                'sermon_videos.transcript',
            )
            ->get();

        foreach ($clips as $clip) {
            $segments = json_decode($clip->transcript, true)['segments'] ?? [];
            $startSegment = $segments[$clip->start_segment_index] ?? null;
            $endSegment = $segments[$clip->end_segment_index] ?? null;

            if ($startSegment && $endSegment) {
                DB::table('sermon_clips')
                    ->where('id', $clip->id)
                    ->update([
                        'starts_at' => $startSegment['start'],
                        'ends_at' => $endSegment['end'],
                    ]);
            }
        }

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn(['pause_before', 'pause_after']);
        });

        // Re-add the virtual duration column.
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->decimal('duration', 8, 2)->virtualAs('ends_at - starts_at')->after('ends_at');
        });
    }
};
