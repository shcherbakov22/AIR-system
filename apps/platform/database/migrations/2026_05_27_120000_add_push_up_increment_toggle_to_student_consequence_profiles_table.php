<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_consequence_profiles', function (Blueprint $table) {
            $table->boolean('increment_push_up_count_per_violation')
                ->default(true)
                ->after('current_push_up_count');
        });
    }

    public function down(): void
    {
        Schema::table('student_consequence_profiles', function (Blueprint $table) {
            $table->dropColumn('increment_push_up_count_per_violation');
        });
    }
};
