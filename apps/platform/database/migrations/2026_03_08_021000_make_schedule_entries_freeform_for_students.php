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
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->foreignId('task_template_id')->nullable()->change();
            $table->string('task_title', 160)->nullable()->after('task_template_id');
            $table->text('task_summary')->nullable()->after('task_title');
            $table->text('task_instructions')->nullable()->after('task_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->dropColumn(['task_title', 'task_summary', 'task_instructions']);
            $table->foreignId('task_template_id')->nullable(false)->change();
        });
    }
};
