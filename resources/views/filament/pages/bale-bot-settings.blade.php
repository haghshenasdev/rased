<x-filament-panels::page>

    <div class="space-y-6">

        {{-- وضعیت --}}
        <x-filament::section
            heading="وضعیت Webhook"
            description="وضعیت اتصال ربات بله به سامانه را مشاهده کنید."
        >

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="rounded-xl border p-4">

                    <div class="text-sm text-gray-500">
                        وضعیت
                    </div>

                    <div class="mt-2 text-lg font-bold">
                        {{ $status ?? 'در حال بررسی...' }}
                    </div>

                </div>

                <div class="rounded-xl border p-4">

                    <div class="text-sm text-gray-500">
                        Webhook فعلی
                    </div>

                    <div class="mt-2 break-all font-mono text-sm">
                        {{ $currentUrl ?: 'تنظیم نشده' }}
                    </div>

                </div>

            </div>

        </x-filament::section>


        {{-- تنظیم Webhook --}}
        <x-filament::section
            heading="تنظیم Webhook"
            description="آدرسی که بله پیام‌های دریافتی ربات را به آن ارسال می‌کند."
        >

            <div class="space-y-4">

                <div>

                    <label class="text-sm font-medium">
                        آدرس Webhook
                    </label>

                    <input
                        type="text"
                        wire:model="webhookUrl"
                        class="fi-input mt-2 block w-full"
                        dir="ltr"
                    >

                </div>

                <div class="flex gap-3">

                    <x-filament::button
                        wire:click="setWebhook"
                        color="success"
                        icon="heroicon-o-link"
                    >
                        تنظیم Webhook
                    </x-filament::button>

                    <x-filament::button
                        wire:click="deleteWebhook"
                        color="danger"
                        icon="heroicon-o-trash"
                    >
                        حذف Webhook
                    </x-filament::button>

                </div>

            </div>

        </x-filament::section>


        {{-- تست ربات --}}
        <x-filament::section
            heading="تست ربات"
            description="برای تست اتصال ربات، پیام آزمایشی ارسال کنید."
        >

            <x-filament::button
                wire:click="sendTestMessage"
                icon="heroicon-o-paper-airplane"
            >
                ارسال پیام تست
            </x-filament::button>

        </x-filament::section>


        {{-- راهنما --}}
        <x-filament::section
            heading="نحوه اتصال کاربران"
        >

            <div class="space-y-3 text-sm">

                <p>
                    ۱. در بخش «مشترکین بله» یک فرد ایجاد کنید.
                </p>

                <p>
                    ۲. سیستم یک توکن اختصاصی برای او ایجاد می‌کند.
                </p>

                <p>
                    ۳. توکن را برای فرد ارسال کنید.
                </p>

                <p>
                    ۴. فرد توکن را برای ربات بله ارسال می‌کند.
                </p>

                <p>
                    ۵. سیستم به صورت خودکار
                    <code>chat_id</code>
                    فرد را ثبت می‌کند.
                </p>

                <p>
                    ۶. از این به بعد اخبار جدید برای او ارسال می‌شود.
                </p>

            </div>

        </x-filament::section>

    </div>

</x-filament-panels::page>
