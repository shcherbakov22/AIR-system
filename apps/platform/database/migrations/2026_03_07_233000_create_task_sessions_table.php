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
        Schema::create('task_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_assignment_id')->nullable()->constrained('task_assignments')->nullOnDelete();
            $table->foreignId('task_template_id')->nullable()->constrained('task_templates')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('task_title_snapshot', 160);
            $table->text('task_summary_snapshot')->nullable();
            $table->text('task_instructions_snapshot')->nullable();
            $table->text('assignment_notes_snapshot')->nullable();
            $table->unsignedSmallInteger('planned_duration_minutes')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stopped_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['student_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_sessions');
    }
};
