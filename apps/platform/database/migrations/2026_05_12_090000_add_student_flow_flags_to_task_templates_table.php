<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->boolean('can_end_early')->default(false)->after('requires_internet');
            $table->boolean('can_interrupt_schedule')->default(false)->after('can_end_early');
        });
    }

    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropColumn(['can_end_early', 'can_interrupt_schedule']);
        });
    }
};
