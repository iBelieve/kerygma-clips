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
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->decimal('starts_at', 8, 2)->nullable()->after('end_segment_index');
            $table->decimal('ends_at', 8, 2)->nullable()->after('starts_at');
        });

        // Backfill existing clips from their sermon video transcript segments.
        $clips = DB::table('sermon_clips')
            ->join('sermon_videos', 'sermon_clips.sermon_video_id', '=', 'sermon_videos.id')
            ->select('sermon_clips.id', 'sermon_clips.start_segment_index', 'sermon_clips.end_segment_index', 'sermon_videos.transcript')
            ->get();

        foreach ($clips as $clip) {
            $segments = json_decode($clip->transcript, true)['segments'] ?? [];
            $startSegment = $segments[$clip->start_segment_index] ?? null;
            $endSegment = $segments[$clip->end_segment_index] ?? null;

            if (! $startSegment || ! $endSegment) {
                throw new \RuntimeException("Sermon clip {$clip->id} has segment indices that are out of bounds.");
            }

            DB::table('sermon_clips')
                ->where('id', $clip->id)
                ->update([
                    'starts_at' => $startSegment['start'],
                    'ends_at' => $endSegment['end'],
                ]);
        }

        // Now that all rows are populated, make the columns non-nullable.
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->decimal('starts_at', 8, 2)->nullable(false)->change();
            $table->decimal('ends_at', 8, 2)->nullable(false)->change();
        });

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->decimal('duration', 8, 2)->virtualAs('ends_at - starts_at')->after('ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the virtual `duration` column first because it references
        // `ends_at - starts_at`. SQLite will error if you try to drop a
        // column that a virtual/generated column still depends on.
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('duration');
        });

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
