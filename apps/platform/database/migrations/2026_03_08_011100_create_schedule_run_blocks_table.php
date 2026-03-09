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
        Schema::create('schedule_run_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_entry_id')->nullable()->constrained('schedule_entries')->nullOnDelete();
            $table->foreignId('task_template_id')->nullable()->constrained('task_templates')->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('status', 20)->default('pending');
            $table->string('start_time_snapshot', 5);
            $table->unsignedSmallInteger('duration_minutes_snapshot');
            $table->string('task_title_snapshot', 160);
            $table->text('task_summary_snapshot')->nullable();
            $table->text('task_instructions_snapshot')->nullable();
            $table->text('entry_notes_snapshot')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['schedule_run_id', 'position']);
            $table->index(['schedule_run_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_run_blocks');
    }
};
