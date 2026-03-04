<?php

use App\Models\SermonClip;
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
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
        });

        foreach (SermonClip::all() as $clip) {
            $clip->update(['description' => $clip->getTranscriptText()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
