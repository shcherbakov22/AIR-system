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
        Schema::table('task_sessions', function (Blueprint $table) {
            $table->foreignId('schedule_run_id')
                ->nullable()
                ->after('task_assignment_id')
                ->constrained('schedule_runs')
                ->nullOnDelete();

            $table->foreignId('schedule_run_block_id')
                ->nullable()
                ->after('schedule_run_id')
                ->constrained('schedule_run_blocks')
                ->nullOnDelete();

            $table->index(['schedule_run_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_sessions', function (Blueprint $table) {
            $table->dropIndex(['schedule_run_id', 'status']);
            $table->dropConstrainedForeignId('schedule_run_block_id');
            $table->dropConstrainedForeignId('schedule_run_id');
        });
    }
};
