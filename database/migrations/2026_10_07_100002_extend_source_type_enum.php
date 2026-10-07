<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sources') || !Schema::hasColumn('sources', 'type')) {
            return;
        }

        try {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE sources MODIFY type VARCHAR(40) NOT NULL");
            }
        } catch (\Throwable) {
            // Some hosting providers restrict ALTER TABLE. The rest of the migration remains usable.
        }
    }

    public function down(): void {}
};
