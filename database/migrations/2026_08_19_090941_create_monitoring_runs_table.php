<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_runs', function (Blueprint $table) {
            $table->id();

            $table->timestamp('started_at');

            $table->timestamp('finished_at')->nullable();

            $table->unsignedInteger('sources_count')->default(0);

            $table->unsignedInteger('items_read')->default(0);

            $table->unsignedInteger('items_matched')->default(0);

            $table->unsignedInteger('items_saved')->default(0);

            $table->enum('status', [
                'running',
                'completed',
                'failed',
            ])->default('running');

            $table->text('error')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_runs');
    }
};
