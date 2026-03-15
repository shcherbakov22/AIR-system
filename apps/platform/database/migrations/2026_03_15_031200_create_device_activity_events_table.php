<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_device_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('app_name', 190)->nullable();
            $table->string('window_title', 255)->nullable();
            $table->string('browser_domain', 255)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->index(['student_device_id', 'event_type', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_activity_events');
    }
};
