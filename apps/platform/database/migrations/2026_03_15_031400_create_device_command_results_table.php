<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_command_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_command_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_device_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->json('payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_command_results');
    }
};
