<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->timestampTz('transcription_started_at')->nullable()->after('transcript_error');
            $table->timestampTz('transcription_completed_at')->nullable()->after('transcription_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->dropColumn(['transcription_started_at', 'transcription_completed_at']);
        });
    }
};
