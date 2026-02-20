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

        // Virtual generated columns must be added in a separate ALTER TABLE statement
        // because SQLite does not support adding multiple columns in a single statement.
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->integer('transcription_duration')->nullable()->virtualAs(
                'CAST((julianday(transcription_completed_at) - julianday(transcription_started_at)) * 86400 AS INTEGER)'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->dropColumn(['transcription_duration', 'transcription_started_at', 'transcription_completed_at']);
        });
    }
};
