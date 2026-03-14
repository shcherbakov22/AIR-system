<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('chat_messages')
            ->where('channel', 'announcement')
            ->update(['student_id' => null]);

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $fallbackStudentId = DB::table('students')->min('id');

        if ($fallbackStudentId === null && DB::table('chat_messages')->where('channel', 'announcement')->exists()) {
            throw new RuntimeException('Cannot restore non-null announcement student_id without any students.');
        }

        if ($fallbackStudentId !== null) {
            DB::table('chat_messages')
                ->where('channel', 'announcement')
                ->whereNull('student_id')
                ->update(['student_id' => $fallbackStudentId]);
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable(false)->change();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }
};
