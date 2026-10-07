<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sources', 'parent_id')) {
            Schema::table('sources', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('id')
                    ->constrained('sources')->nullOnDelete();
                $table->string('profile_image_path')->nullable()->after('identifier');
                $table->text('profile_image_url')->nullable()->after('profile_image_path');
                $table->unsignedBigInteger('default_category_id')->nullable()->after('profile_image_url');
                $table->boolean('auto_categorize')->default(true)->after('default_category_id');
                $table->boolean('ignore_link_keyword')->default(true)->after('auto_categorize');
                $table->index(['parent_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->json('keywords')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('categories')) {
            foreach (['سیاسی','اجتماعی','اقتصادی','بین‌الملل','فناوری','ورزشی','معاند','سایر'] as $name) {
                \DB::table('categories')->updateOrInsert(
                    ['name' => $name],
                    ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        if (!Schema::hasColumn('source_items', 'featured_image_url')) {
            Schema::table('source_items', function (Blueprint $table) {
                $table->text('featured_image_url')->nullable()->after('url');
                $table->foreignId('category_id')->nullable()->after('matched_keyword')
                    ->constrained('categories')->nullOnDelete();
                $table->foreignId('duplicate_of_id')->nullable()->after('category_id')
                    ->constrained('source_items')->nullOnDelete();
                $table->decimal('similarity_percent', 5, 2)->nullable()->after('duplicate_of_id');
                $table->boolean('is_repost')->default(false)->after('similarity_percent');
                $table->index(['category_id', 'published_at']);
                $table->index(['duplicate_of_id', 'published_at']);
            });
        }

        if (!Schema::hasColumn('monitoring_logs', 'error_type')) {
            Schema::table('monitoring_logs', function (Blueprint $table) {
                $table->string('error_type')->nullable()->after('status');
                $table->text('context')->nullable()->after('message');
                $table->index(['source_id', 'status', 'created_at']);
            });
        }
        if (Schema::hasTable('monitoring_logs')) {
            foreach (\DB::table('monitoring_logs')->select('source_id')->distinct()->pluck('source_id') as $sourceId) {
                $keep = \DB::table('monitoring_logs')
                    ->where('source_id', $sourceId)
                    ->where('status', 'failed')
                    ->orderByDesc('id')
                    ->limit(3)
                    ->pluck('id');
                if ($keep->isNotEmpty()) {
                    \DB::table('monitoring_logs')
                        ->where('source_id', $sourceId)
                        ->where('status', 'failed')
                        ->whereNotIn('id', $keep->all())
                        ->delete();
                }
            }
        }
    }

    public function down(): void
    {
        // Deliberately conservative: this migration is intended for production upgrades.
    }
};
