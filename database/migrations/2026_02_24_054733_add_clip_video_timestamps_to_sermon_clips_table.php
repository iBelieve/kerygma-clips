<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->timestampTz('clip_video_started_at')->nullable()->after('clip_video_error');
        });

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->timestampTz('clip_video_completed_at')->nullable()->after('clip_video_started_at');
        });

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->integer('clip_video_duration')->nullable()->virtualAs(
                'CAST(ROUND((julianday(clip_video_completed_at) - julianday(clip_video_started_at)) * 86400) AS INTEGER)'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('clip_video_duration');
        });

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('clip_video_completed_at');
        });

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('clip_video_started_at');
        });
    }
};
