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
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->renameColumn('ai_title', 'title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->renameColumn('title', 'ai_title');
        });
    }
};
