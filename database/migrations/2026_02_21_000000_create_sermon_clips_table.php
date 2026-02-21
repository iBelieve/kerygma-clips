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
        Schema::create('sermon_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sermon_video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('start_segment_index');
            $table->unsignedInteger('end_segment_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sermon_clips');
    }
};
