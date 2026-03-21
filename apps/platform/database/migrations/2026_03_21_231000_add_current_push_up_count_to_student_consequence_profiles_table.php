<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_consequence_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('current_push_up_count')->default(10)->after('default_push_up_count');
        });

        DB::table('student_consequence_profiles')
            ->whereNull('current_push_up_count')
            ->update(['current_push_up_count' => 10]);
    }

    public function down(): void
    {
        Schema::table('student_consequence_profiles', function (Blueprint $table) {
            $table->dropColumn('current_push_up_count');
        });
    }
};
