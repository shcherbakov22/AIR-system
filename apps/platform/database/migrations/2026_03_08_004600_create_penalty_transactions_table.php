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
        Schema::create('penalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penalty_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('violation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->integer('delta_units');
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['penalty_account_id', 'recorded_at']);
            $table->index(['type', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penalty_transactions');
    }
};
