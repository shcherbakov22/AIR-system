<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_overseer_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_overseer_decision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender', 30);
            $table->text('body');
            $table->boolean('is_final_decision')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ai_overseer_decision_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_overseer_messages');
    }
};
