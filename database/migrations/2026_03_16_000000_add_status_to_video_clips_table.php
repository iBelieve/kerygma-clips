<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_clips', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('excerpt');
        });

        DB::table('video_clips')
            ->whereNotNull('scheduled_date')
            ->where('scheduled_date', '<', now()->toDateString())
            ->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('video_clips', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
