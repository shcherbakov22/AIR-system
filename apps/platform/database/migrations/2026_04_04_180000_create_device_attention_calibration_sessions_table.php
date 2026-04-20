<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_attention_calibration_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_device_id');
            $table->foreign('student_device_id', 'attention_sessions_device_fk')
                ->references('id')
                ->on('student_devices')
                ->cascadeOnDelete();
            $table->uuid('session_uuid')->unique();
            $table->string('provider', 32)->default('eyetheia');
            $table->string('status', 32)->default('collecting');
            $table->unsignedInteger('sample_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('training_requested_at')->nullable();
            $table->timestamp('model_ready_at')->nullable();
            $table->string('model_version', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_attention_calibration_sessions');
    }
};
