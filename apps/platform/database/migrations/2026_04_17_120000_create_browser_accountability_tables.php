<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('browser_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('task_template_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('effect', 16);
            $table->string('match_type', 32)->default('domain_tree');
            $table->string('value', 255);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'task_template_id', 'effect', 'match_type'], 'browser_policy_scope_idx');
            $table->index(['task_template_id', 'value'], 'browser_policy_template_value_idx');
            $table->unique(['student_id', 'task_template_id', 'effect', 'match_type', 'value'], 'browser_policy_rule_unique');
        });

        Schema::create('browser_visit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matched_rule_id')->nullable()->constrained('browser_policy_rules')->nullOnDelete();
            $table->string('mode', 32);
            $table->string('decision', 24);
            $table->string('url', 2048)->nullable();
            $table->string('host', 255);
            $table->string('registrable_domain', 255);
            $table->string('page_title', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('visited_at');
            $table->timestamps();

            $table->index(['student_id', 'visited_at']);
            $table->index(['student_id', 'registrable_domain'], 'browser_visit_student_domain_idx');
            $table->index(['student_device_id', 'visited_at'], 'browser_visit_device_time_idx');
        });

        Schema::create('browser_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_rule_id')->nullable()->constrained('browser_policy_rules')->nullOnDelete();
            $table->string('requested_url', 2048);
            $table->string('host', 255);
            $table->string('registrable_domain', 255);
            $table->text('reason')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('mentor_note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status', 'created_at'], 'browser_access_status_idx');
            $table->index(['student_id', 'registrable_domain'], 'browser_access_student_domain_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('browser_access_requests');
        Schema::dropIfExists('browser_visit_logs');
        Schema::dropIfExists('browser_policy_rules');
    }
};
