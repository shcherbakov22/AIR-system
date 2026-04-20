<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_device_installed_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_device_id')->constrained()->cascadeOnDelete();
            $table->string('app_key', 190);
            $table->string('display_name', 190);
            $table->string('display_version', 120)->nullable();
            $table->string('publisher', 190)->nullable();
            $table->string('install_location', 255)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['student_device_id', 'app_key'], 'device_apps_device_key_unique');
            $table->index(['student_device_id', 'display_name'], 'device_apps_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_device_installed_apps');
    }
};
