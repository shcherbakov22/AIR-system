<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_devices', function (Blueprint $table) {
            $table->string('remote_access_username', 160)->nullable()->after('internet_access_mode');
            $table->text('remote_access_password')->nullable()->after('remote_access_username');
            $table->boolean('remote_control_ready')->default(false)->after('remote_access_password');
            $table->timestamp('remote_control_last_checked_at')->nullable()->after('remote_control_ready');
            $table->text('remote_control_failure_reason')->nullable()->after('remote_control_last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_devices', function (Blueprint $table) {
            $table->dropColumn([
                'remote_access_username',
                'remote_access_password',
                'remote_control_ready',
                'remote_control_last_checked_at',
                'remote_control_failure_reason',
            ]);
        });
    }
};
