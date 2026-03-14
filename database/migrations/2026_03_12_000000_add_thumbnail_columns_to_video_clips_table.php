<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_clips', function (Blueprint $table) {
            $table->string('thumbnail_status')->default('pending');
            $table->string('thumbnail_path')->nullable();
            $table->text('thumbnail_error')->nullable();
            $table->timestampTz('thumbnail_started_at')->nullable();
            $table->timestampTz('thumbnail_completed_at')->nullable();
        });

        // Virtual generated column must be added separately because it
        // references columns from the ALTER TABLE statement above.
        Schema::table('video_clips', function (Blueprint $table) {
            $table->integer('thumbnail_duration')->nullable()->virtualAs(
                'CAST(ROUND((julianday(thumbnail_completed_at) - julianday(thumbnail_started_at)) * 86400) AS INTEGER)'
            );
        });
    }

    public function down(): void
    {
        // Drop the virtual column first since it references other columns being dropped.
        Schema::table('video_clips', function (Blueprint $table) {
            $table->dropColumn('thumbnail_duration');
        });

        Schema::table('video_clips', function (Blueprint $table) {
            $table->dropColumn([
                'thumbnail_completed_at',
                'thumbnail_started_at',
                'thumbnail_error',
                'thumbnail_path',
                'thumbnail_status',
            ]);
        });
    }
};
