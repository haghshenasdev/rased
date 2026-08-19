<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_items', function (Blueprint $table) {
            $table->string('matched_keyword', 500)
                ->nullable()
                ->after('matched_content');

            $table->index('matched_keyword');
        });
    }

    public function down(): void
    {
        Schema::table('source_items', function (Blueprint $table) {
            $table->dropIndex(['matched_keyword']);
            $table->dropColumn('matched_keyword');
        });
    }
};
