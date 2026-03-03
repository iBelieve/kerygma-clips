<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->string('subtitle')->nullable();
            $table->string('scripture')->nullable();
            $table->string('preacher')->nullable();
            $table->string('color')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sermon_videos', function (Blueprint $table) {
            $table->dropColumn(['subtitle', 'scripture', 'preacher', 'color']);
        });
    }
};
