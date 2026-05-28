<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_settings', function (Blueprint $table) {
            $table->unsignedInteger('screen_capture_interval_seconds')->default(30)->after('preferred_timezone');
            $table->unsignedInteger('camera_capture_interval_seconds')->default(30)->after('screen_capture_interval_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('student_settings', function (Blueprint $table) {
            $table->dropColumn([
                'screen_capture_interval_seconds',
                'camera_capture_interval_seconds',
            ]);
        });
    }
};
