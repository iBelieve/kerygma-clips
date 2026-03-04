<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('fb_reel_description');
        });
    }

    public function down(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->text('fb_reel_description')->nullable();
        });
    }
};
