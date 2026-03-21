<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_devices', function (Blueprint $table) {
            if (Schema::hasColumn('student_devices', 'remote_access_username')) {
                $table->dropColumn('remote_access_username');
            }

            if (Schema::hasColumn('student_devices', 'remote_access_password')) {
                $table->dropColumn('remote_access_password');
            }

            if (! Schema::hasColumn('student_devices', 'remote_control_active')) {
                $table->boolean('remote_control_active')->default(false)->after('remote_control_ready');
            }

            if (! Schema::hasColumn('student_devices', 'remote_control_port')) {
                $table->unsignedInteger('remote_control_port')->nullable()->after('remote_control_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_devices', function (Blueprint $table) {
            if (Schema::hasColumn('student_devices', 'remote_control_port')) {
                $table->dropColumn('remote_control_port');
            }

            if (Schema::hasColumn('student_devices', 'remote_control_active')) {
                $table->dropColumn('remote_control_active');
            }

            if (! Schema::hasColumn('student_devices', 'remote_access_username')) {
                $table->string('remote_access_username')->nullable()->after('internet_access_mode');
            }

            if (! Schema::hasColumn('student_devices', 'remote_access_password')) {
                $table->text('remote_access_password')->nullable()->after('remote_access_username');
            }
        });
    }
};
