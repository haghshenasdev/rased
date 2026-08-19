<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monitoring_run_id')
                ->constrained('monitoring_runs')
                ->cascadeOnDelete();

            $table->foreignId('source_id')
                ->constrained('sources')
                ->cascadeOnDelete();

            $table->unsignedInteger('items_read')->default(0);

            $table->unsignedInteger('items_matched')->default(0);

            $table->unsignedInteger('items_saved')->default(0);

            $table->enum('status', [
                'success',
                'failed',
            ])->default('success');

            $table->text('message')->nullable();

            $table->timestamps();

            $table->index([
                'monitoring_run_id',
                'source_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_logs');
    }
};
