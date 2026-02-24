<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->string('vertical_video_status')->default('pending')->after('duration');
        });

        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->string('vertical_video_path')->nullable()->after('vertical_video_status');
        });

        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->text('vertical_video_error')->nullable()->after('vertical_video_path');
        });

        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->timestampTz('vertical_video_started_at')->nullable()->after('vertical_video_error');
        });

        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->timestampTz('vertical_video_completed_at')->nullable()->after('vertical_video_started_at');
        });

        // Virtual generated columns must be added in a separate ALTER TABLE statement
        // because SQLite does not support adding multiple columns in a single statement.
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->integer('vertical_video_duration')->nullable()->virtualAs(
                'CAST(ROUND((julianday(vertical_video_completed_at) - julianday(vertical_video_started_at)) * 86400) AS INTEGER)'
            );
        });

        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->unsignedTinyInteger('vertical_video_crop_center')->default(50)->after('vertical_video_duration');
        });
    }

    public function down(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->dropColumn([
                'vertical_video_status',
                'vertical_video_path',
                'vertical_video_error',
                'vertical_video_started_at',
                'vertical_video_completed_at',
                'vertical_video_duration',
                'vertical_video_crop_center',
            ]);
        });
    }
};
