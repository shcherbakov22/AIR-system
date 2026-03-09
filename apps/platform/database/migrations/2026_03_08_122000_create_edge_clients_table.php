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
        Schema::create('edge_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_key', 120)->unique();
            $table->string('client_type', 32);
            $table->string('label', 160);
            $table->string('version', 64)->nullable();
            $table->json('capabilities')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 64)->nullable();
            $table->text('last_user_agent')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamps();

            $table->index(['client_type', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edge_clients');
    }
};
