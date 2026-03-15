<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_devices', function (Blueprint $table) {
            $table->string('last_ipv4', 45)->nullable()->after('last_seen_ip');
            $table->string('last_mac_address', 64)->nullable()->after('last_ipv4');
            $table->string('last_gateway_ipv4', 45)->nullable()->after('last_mac_address');
            $table->string('network_adapter_name', 160)->nullable()->after('last_gateway_ipv4');
        });
    }

    public function down(): void
    {
        Schema::table('student_devices', function (Blueprint $table) {
            $table->dropColumn([
                'last_ipv4',
                'last_mac_address',
                'last_gateway_ipv4',
                'network_adapter_name',
            ]);
        });
    }
};
