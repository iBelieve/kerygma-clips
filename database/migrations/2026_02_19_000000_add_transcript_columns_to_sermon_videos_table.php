<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->json('transcript')->nullable()->after('transcript_status');
            $table->text('transcript_error')->nullable()->after('transcript');
        });
    }

    public function down(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->dropColumn(['transcript', 'transcript_error']);
        });
    }
};
