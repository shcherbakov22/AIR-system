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
        Schema::create('legacy_record_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_system', 64);
            $table->string('legacy_table', 120);
            $table->string('legacy_key', 160);
            $table->nullableMorphs('target');
            $table->string('status', 32)->default('linked');
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['legacy_system', 'legacy_table']);
            $table->unique([
                'import_run_id',
                'legacy_system',
                'legacy_table',
                'legacy_key',
                'target_type',
                'target_id',
            ], 'legacy_record_links_unique_target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_record_links');
    }
};
