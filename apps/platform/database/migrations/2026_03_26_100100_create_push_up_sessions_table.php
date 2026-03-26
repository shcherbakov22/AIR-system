<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_up_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('violation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('push_up_station_id')->nullable()->constrained('push_up_stations')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('required_push_ups');
            $table->json('configuration');
            $table->unsignedInteger('current_rep')->default(0);
            $table->unsignedInteger('current_set')->default(1);
            $table->text('notes')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['violation_id', 'status']);
            $table->index(['push_up_station_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_up_sessions');
    }
};
