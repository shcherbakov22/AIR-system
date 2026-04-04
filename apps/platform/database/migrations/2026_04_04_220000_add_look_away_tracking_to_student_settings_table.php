<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_settings', function (Blueprint $table) {
            $table->unsignedInteger('look_away_event_threshold')->default(3)->after('can_use_ad_hoc_timer');
            $table->unsignedInteger('look_away_event_count')->default(0)->after('look_away_event_threshold');
            $table->foreignId('look_away_task_session_id')->nullable()->after('look_away_event_count')->constrained('task_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('look_away_task_session_id');
            $table->dropColumn([
                'look_away_event_threshold',
                'look_away_event_count',
            ]);
        });
    }
};
