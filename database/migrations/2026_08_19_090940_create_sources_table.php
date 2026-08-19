<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();

            // نام منبع
            $table->string('name');

            // نوع منبع
            $table->enum('type', [
                'rss',
                'html',
                'javascript',
                'eitaa',
            ]);

            // آدرس اصلی منبع
            $table->text('url');

            // شناسه منبع
            // مثلاً Hamase4 برای ایتا
            $table->string('identifier')->nullable();

            // تنظیمات اختصاصی منبع
            // Selectorهای HTML و تنظیمات احتمالی JS و ...
            $table->json('settings')->nullable();

            // آخرین آیتمی که خوانده شده
            $table->string('last_item_id')->nullable();

            // آخرین URL خوانده شده
            $table->text('last_item_url')->nullable();

            // آخرین زمان بررسی
            $table->timestamp('last_read_at')->nullable();

            // فعال / غیرفعال
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // برای جستجوی سریع
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
