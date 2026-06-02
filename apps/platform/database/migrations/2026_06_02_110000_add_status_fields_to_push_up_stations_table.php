<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_up_stations', function (Blueprint $table) {
            $table->string('firmware_version', 60)->nullable()->after('name');
            $table->string('ip_address', 80)->nullable()->after('firmware_version');
            $table->string('state', 80)->nullable()->after('ip_address');
            $table->string('sensor_status', 80)->nullable()->after('state');
            $table->unsignedInteger('free_heap')->nullable()->after('sensor_status');
            $table->integer('distance')->nullable()->after('free_heap');
            $table->json('debug_payload')->nullable()->after('distance');
        });
    }

    public function down(): void
    {
        Schema::table('push_up_stations', function (Blueprint $table) {
            $table->dropColumn([
                'firmware_version',
                'ip_address',
                'state',
                'sensor_status',
                'free_heap',
                'distance',
                'debug_payload',
            ]);
        });
    }
};
