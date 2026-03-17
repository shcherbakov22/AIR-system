<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_devices', function (Blueprint $table) {
            $table->string('internet_access_mode', 32)
                ->default('allow_all')
                ->after('network_adapter_name');
        });
    }

    public function down(): void
    {
        Schema::table('student_devices', function (Blueprint $table) {
            $table->dropColumn('internet_access_mode');
        });
    }
};
