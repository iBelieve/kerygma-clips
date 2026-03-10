<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('sermon_videos', 'videos');
        Schema::rename('sermon_clips', 'video_clips');

        Schema::table('video_clips', function (Blueprint $table) {
            $table->renameColumn('sermon_video_id', 'video_id');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->string('type')->default('sermon')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('video_clips', function (Blueprint $table) {
            $table->renameColumn('video_id', 'sermon_video_id');
        });

        Schema::rename('video_clips', 'sermon_clips');
        Schema::rename('videos', 'sermon_videos');
    }
};
