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
            $table->boolean('title_manually_edited')->default(false)->after('title');
            $table->boolean('excerpt_manually_edited')->default(false)->after('excerpt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_clips', function (Blueprint $table) {
            $table->dropColumn(['title_manually_edited', 'excerpt_manually_edited']);
        });
    }
};
