<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speech_announcements', function (Blueprint $table) {
            $table->timestamp('processing_started_at')->nullable()->after('meta')->index();
            $table->string('processing_host')->nullable()->after('processing_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('speech_announcements', function (Blueprint $table) {
            $table->dropColumn([
                'processing_started_at',
                'processing_host',
            ]);
        });
    }
};
