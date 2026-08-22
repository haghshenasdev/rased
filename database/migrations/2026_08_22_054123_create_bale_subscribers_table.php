<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bale_subscribers', function (Blueprint $table) {
            $table->id();

            // نام فرد
            $table->string('name');

            // شماره موبایل یا توضیح اختیاری
            $table->string('phone')->nullable();

            // توکنی که فرد برای ربات ارسال می‌کند
            $table->string('token')->unique();

            // شناسه چت در بله
            $table->string('chat_id')->nullable()->unique();

            // آیا ارسال خبر فعال است؟
            $table->boolean('is_active')->default(true);

            // زمان اتصال موفق به ربات
            $table->timestamp('connected_at')->nullable();

            // آخرین ارسال خبر
            $table->timestamp('last_sent_at')->nullable();

            $table->timestamps();

            $table->index('is_active');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bale_subscribers');
    }
};
