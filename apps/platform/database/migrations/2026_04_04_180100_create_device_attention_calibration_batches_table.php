<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_attention_calibration_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_attention_calibration_session_id', 'session_id')
                ->constrained('device_attention_calibration_sessions')
                ->cascadeOnDelete();
            $table->unsignedInteger('sequence_number')->default(1);
            $table->unsignedInteger('sample_count')->default(0);
            $table->timestamp('captured_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_attention_calibration_batches');
    }
};
