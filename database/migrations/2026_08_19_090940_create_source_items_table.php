<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('source_id')
                ->constrained('sources')
                ->cascadeOnDelete();

            // شناسه مطلب در منبع
            $table->string('external_id')->nullable();

            // عنوان
            $table->text('title');

            // لینک مطلب
            $table->text('url')->nullable();

            // محتوای کامل
            $table->longText('content')->nullable();

            // فقط پاراگراف‌هایی که Keyword در آنها پیدا شده
            $table->longText('matched_content')->nullable();

            // تاریخ انتشار در منبع
            $table->timestamp('published_at')->nullable();

            // اطلاعات خام منبع
            $table->json('raw_data')->nullable();

            $table->timestamps();

            /*
             * جلوگیری از ذخیره مجدد یک مطلب
             *
             * external_id ممکن است null باشد،
             * بنابراین برای مطالب بدون ID از URL/hash
             * در Service استفاده خواهیم کرد.
             */
            $table->unique(
                ['source_id', 'external_id'],
                'source_items_source_external_unique'
            );

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_items');
    }
};
