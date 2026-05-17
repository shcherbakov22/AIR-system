<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('resumed_from_task_session_id')
                ->nullable()
                ->after('task_template_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('task_sessions', function (Blueprint $table) {
            $table->dropIndex(['resumed_from_task_session_id']);
            $table->dropColumn('resumed_from_task_session_id');
        });
    }
};
