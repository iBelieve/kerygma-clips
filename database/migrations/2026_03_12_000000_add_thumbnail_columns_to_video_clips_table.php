<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_clips', function (Blueprint $table) {
            $table->string('thumbnail_status')->default('pending')->after('clip_video_completed_at');
            $table->string('thumbnail_path')->nullable()->after('thumbnail_status');
            $table->text('thumbnail_error')->nullable()->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('video_clips', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_status', 'thumbnail_path', 'thumbnail_error']);
        });
    }
};
