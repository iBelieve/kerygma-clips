<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('video_clips', function (Blueprint $table) {
            $table->string('youtube_video_id')->nullable();
            $table->string('youtube_status')->nullable();
            $table->text('youtube_error')->nullable();
            $table->timestamp('youtube_published_at')->nullable();
            $table->timestamp('youtube_uploaded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_clips', function (Blueprint $table) {
            $table->dropColumn([
                'youtube_video_id',
                'youtube_status',
                'youtube_error',
                'youtube_published_at',
                'youtube_uploaded_at',
            ]);
        });
    }
};
