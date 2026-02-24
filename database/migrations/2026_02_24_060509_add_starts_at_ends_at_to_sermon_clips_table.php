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
            $table->decimal('starts_at', 8, 2)->nullable()->after('end_segment_index');
            $table->decimal('ends_at', 8, 2)->nullable()->after('starts_at');
            $table->decimal('duration', 8, 2)->nullable()->virtualAs('ends_at - starts_at')->after('ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the virtual `duration` column first because it references
        // `ends_at - starts_at`. SQLite will error if you try to drop a
        // column that a virtual/generated column still depends on.
        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn('duration');
        });

        Schema::table('sermon_clips', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
