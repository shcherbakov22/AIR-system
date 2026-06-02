<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_up_station_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_up_station_id')->nullable()->constrained('push_up_stations')->nullOnDelete();
            $table->string('station_key', 120)->index();
            $table->string('level', 20)->default('info')->index();
            $table->string('event', 120)->index();
            $table->string('message', 1000)->nullable();
            $table->string('firmware_version', 60)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('ip_address', 80)->nullable();
            $table->unsignedInteger('free_heap')->nullable();
            $table->integer('distance')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['station_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_up_station_logs');
    }
};
