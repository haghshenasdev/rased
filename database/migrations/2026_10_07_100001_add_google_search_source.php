<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sources')) {
            Schema::table('sources', function (Blueprint $table) {
                // Existing enum columns cannot be safely changed on every MySQL version.
                // Google source type is therefore stored in the existing string-like column
                // only when the original database already supports it. A conversion migration
                // is supplied below for MySQL deployments.
            });
        }
    }

    public function down(): void {}
};
