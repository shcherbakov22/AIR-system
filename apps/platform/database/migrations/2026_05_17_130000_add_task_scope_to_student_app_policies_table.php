<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_app_policies', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'app_key']);
            $table->foreignId('task_template_id')
                ->nullable()
                ->after('student_id')
                ->constrained()
                ->nullOnDelete();
            $table->index(['student_id', 'task_template_id', 'status'], 'student_app_policies_scope_status_idx');
            $table->index(['student_id', 'task_template_id', 'app_key'], 'student_app_policies_scope_app_idx');
        });
    }

    public function down(): void
    {
        Schema::table('student_app_policies', function (Blueprint $table) {
            $table->dropIndex('student_app_policies_scope_status_idx');
            $table->dropIndex('student_app_policies_scope_app_idx');
            $table->dropConstrainedForeignId('task_template_id');
            $table->unique(['student_id', 'app_key']);
        });
    }
};
