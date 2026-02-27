<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->string('fb_reel_status')->default('pending');
            $table->string('fb_reel_id')->nullable();
            $table->text('fb_reel_error')->nullable();
            $table->text('fb_reel_description')->nullable();
            $table->timestampTz('fb_reel_started_at')->nullable();
            $table->timestampTz('fb_reel_completed_at')->nullable();
            $table->timestampTz('fb_reel_published_at')->nullable();
            $table->timestampTz('fb_reel_scheduled_for')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn([
                'fb_reel_status',
                'fb_reel_id',
                'fb_reel_error',
                'fb_reel_description',
                'fb_reel_started_at',
                'fb_reel_completed_at',
                'fb_reel_published_at',
                'fb_reel_scheduled_for',
            ]);
        });
    }
};
