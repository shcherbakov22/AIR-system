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
        Schema::create('edge_client_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edge_client_id')->constrained()->cascadeOnDelete();
            $table->timestamp('received_at');
            $table->string('ip_address', 64)->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->index(['edge_client_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edge_client_heartbeats');
    }
};
