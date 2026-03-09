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
        Schema::create('schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_template_id')->constrained('schedule_templates')->cascadeOnDelete();
            $table->foreignId('task_template_id')->constrained('task_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['schedule_template_id', 'position']);
            $table->index(['schedule_template_id', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_entries');
    }
};
