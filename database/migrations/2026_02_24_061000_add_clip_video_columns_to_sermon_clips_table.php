<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->string('clip_video_status')->default('pending')->after('end_segment_index');
            $table->string('clip_video_path')->nullable()->after('clip_video_status');
            $table->text('clip_video_error')->nullable()->after('clip_video_path');
            $table->timestampTz('clip_video_started_at')->nullable()->after('clip_video_error');
            $table->timestampTz('clip_video_completed_at')->nullable()->after('clip_video_started_at');
        });

        // Virtual generated column must be added separately because it
        // references columns from the ALTER TABLE statement above.
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->integer('clip_video_duration')->nullable()->virtualAs(
                'CAST(ROUND((julianday(clip_video_completed_at) - julianday(clip_video_started_at)) * 86400) AS INTEGER)'
            );
        });
    }

    public function down(): void
    {
        // Drop the virtual column first since it references other columns being dropped.
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('clip_video_duration');
        });

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn([
                'clip_video_completed_at',
                'clip_video_started_at',
                'clip_video_error',
                'clip_video_path',
                'clip_video_status',
            ]);
        });
    }
};
