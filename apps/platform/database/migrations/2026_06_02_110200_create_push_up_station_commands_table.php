<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_up_station_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_up_station_id')->nullable()->constrained('push_up_stations')->nullOnDelete();
            $table->string('station_key', 120)->index();
            $table->string('command', 80);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();

            $table->index(['station_key', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_up_station_commands');
    }
};
