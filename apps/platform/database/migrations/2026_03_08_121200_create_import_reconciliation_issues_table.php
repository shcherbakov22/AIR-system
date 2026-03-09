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
        Schema::create('import_reconciliation_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->string('severity', 16)->default('warning');
            $table->string('status', 32)->default('open');
            $table->string('legacy_system', 64)->nullable();
            $table->string('legacy_table', 120)->nullable();
            $table->string('legacy_key', 160)->nullable();
            $table->nullableMorphs('target');
            $table->string('summary', 200);
            $table->text('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_reconciliation_issues');
    }
};
