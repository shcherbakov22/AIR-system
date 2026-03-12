<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_monitor_captures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edge_client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('capture_kind', 20);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->string('task_title_snapshot')->nullable();
            $table->string('source_label')->nullable();
            $table->string('source_version', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'capture_kind', 'captured_at'], 'student_monitor_capture_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_monitor_captures');
    }
};
