<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_monitor_captures', function (Blueprint $table) {
            $table->foreignId('student_device_id')->nullable()->after('edge_client_id')->constrained()->nullOnDelete();
            $table->string('app_name_snapshot', 190)->nullable()->after('task_title_snapshot');
            $table->string('window_title_snapshot', 255)->nullable()->after('app_name_snapshot');
            $table->string('browser_domain_snapshot', 255)->nullable()->after('window_title_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('student_monitor_captures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_device_id');
            $table->dropColumn([
                'app_name_snapshot',
                'window_title_snapshot',
                'browser_domain_snapshot',
            ]);
        });
    }
};
