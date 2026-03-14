<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedInteger('source_width')->nullable();
            $table->unsignedInteger('source_height')->nullable();
            $table->string('source_aspect_ratio')->nullable();
            $table->boolean('is_source_vertical')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'source_width',
                'source_height',
                'source_aspect_ratio',
                'is_source_vertical',
            ]);
        });
    }
};
