<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_overseer_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('violation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_run_block_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mentor_chat_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->string('request_type', 40);
            $table->string('status', 40)->default('mentor_review');
            $table->string('decision', 80)->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->text('student_reason')->nullable();
            $table->text('student_message')->nullable();
            $table->text('mentor_summary')->nullable();
            $table->text('reason')->nullable();
            $table->string('action_taken', 80)->nullable();
            $table->json('context_snapshot')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('model', 120)->nullable();
            $table->string('prompt_version', 40)->default('ai-overseer-v1');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('mentor_notified_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['request_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_overseer_decisions');
    }
};
