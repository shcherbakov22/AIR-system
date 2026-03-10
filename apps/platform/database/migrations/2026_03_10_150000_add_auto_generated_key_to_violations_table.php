<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->string('auto_generated_key')->nullable()->unique()->after('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->dropUnique(['auto_generated_key']);
            $table->dropColumn('auto_generated_key');
        });
    }
};
