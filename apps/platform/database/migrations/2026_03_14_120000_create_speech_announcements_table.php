<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('speech_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('violation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->text('message');
            $table->json('meta')->nullable();
            $table->timestamp('spoken_at')->nullable()->index();
            $table->timestamps();

            $table->index(['kind', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speech_announcements');
    }
};
