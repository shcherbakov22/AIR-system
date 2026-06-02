<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_up_student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            $table->unsignedInteger('sample_count')->default(0);
            $table->float('top_distance')->nullable();
            $table->float('down_distance')->nullable();
            $table->float('amplitude')->nullable();
            $table->float('return_distance')->nullable();
            $table->float('noise_cm')->nullable();
            $table->float('average_rep_duration_ms')->nullable();
            $table->float('confidence')->default(0);
            $table->timestamp('last_calibrated_at')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_up_student_profiles');
    }
};
