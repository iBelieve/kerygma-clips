<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->string('preview_frame_path')->nullable()->after('vertical_video_crop_center');
        });
    }

    public function down(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->dropColumn('preview_frame_path');
        });
    }
};
