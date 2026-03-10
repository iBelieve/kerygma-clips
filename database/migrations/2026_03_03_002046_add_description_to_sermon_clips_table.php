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
            $table->text('description')->nullable()->after('title');
        });

        $clips = DB::table('sermon_clips')
            ->join('sermon_videos', 'sermon_clips.sermon_video_id', '=', 'sermon_videos.id')
            ->select('sermon_clips.id', 'sermon_clips.start_segment_index', 'sermon_clips.end_segment_index', 'sermon_videos.transcript')
            ->get();

        foreach ($clips as $clip) {
            $segments = json_decode($clip->transcript, true)['segments'] ?? [];
            $texts = collect($segments)
                ->slice($clip->start_segment_index, $clip->end_segment_index - $clip->start_segment_index + 1)
                ->pluck('text')
                ->map(fn ($t) => trim($t))
                ->filter()
                ->implode(' ');

            if ($texts !== '') {
                DB::table('sermon_clips')->where('id', $clip->id)->update(['description' => $texts]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
