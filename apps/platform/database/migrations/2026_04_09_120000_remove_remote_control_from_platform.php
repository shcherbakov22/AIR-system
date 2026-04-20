<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('remote_control_sessions');

        Schema::table('student_devices', function (Blueprint $table): void {
            $columns = [
                'remote_access_username',
                'remote_access_password',
                'remote_control_ready',
                'remote_control_active',
                'remote_control_port',
                'remote_control_last_checked_at',
                'remote_control_failure_reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('student_devices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_devices', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_devices', 'remote_access_username')) {
                $table->string('remote_access_username', 160)->nullable()->after('internet_access_mode');
            }
            if (! Schema::hasColumn('student_devices', 'remote_access_password')) {
                $table->text('remote_access_password')->nullable()->after('remote_access_username');
            }
            if (! Schema::hasColumn('student_devices', 'remote_control_ready')) {
                $table->boolean('remote_control_ready')->default(false)->after('remote_access_password');
            }
            if (! Schema::hasColumn('student_devices', 'remote_control_active')) {
                $table->boolean('remote_control_active')->default(false)->after('remote_control_ready');
            }
            if (! Schema::hasColumn('student_devices', 'remote_control_port')) {
                $table->unsignedInteger('remote_control_port')->nullable()->after('remote_control_active');
            }
            if (! Schema::hasColumn('student_devices', 'remote_control_last_checked_at')) {
                $table->timestamp('remote_control_last_checked_at')->nullable()->after('remote_control_port');
            }
            if (! Schema::hasColumn('student_devices', 'remote_control_failure_reason')) {
                $table->text('remote_control_failure_reason')->nullable()->after('remote_control_last_checked_at');
            }
        });

        Schema::create('remote_control_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_token', 80)->unique();
            $table->string('gateway_session_id')->nullable();
            $table->string('target_host', 160);
            $table->string('viewer_path')->nullable();
            $table->string('status', 32)->default('starting');
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }
};
