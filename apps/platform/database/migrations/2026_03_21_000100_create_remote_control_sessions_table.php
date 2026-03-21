<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_control_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_device_id')->constrained('student_devices')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_token', 64)->unique();
            $table->string('gateway_session_id', 64)->nullable();
            $table->string('target_host', 64)->nullable();
            $table->string('viewer_path', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_control_sessions');
    }
};
