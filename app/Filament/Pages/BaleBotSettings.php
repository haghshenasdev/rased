<?php

namespace App\Filament\Pages;

use App\Services\BaleBotService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class BaleBotSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-paper-airplane';

    protected static string|UnitEnum|null $navigationGroup =
        'رصد و اعلان‌ها';

    protected static ?string $navigationLabel =
        'تنظیمات ربات بله';

    protected static ?string $title =
        'تنظیمات ربات بله';

    protected string $view =
        'filament.pages.bale-bot-settings';

    public ?string $webhookUrl = null;

    public ?string $status = null;

    public ?string $currentUrl = null;

    public function mount(): void
    {
        $this->webhookUrl =
            url('/bale/webhook');

        $this->loadWebhookInfo();
    }

    /**
     * دریافت وضعیت Webhook
     */
    public function loadWebhookInfo(): void
    {
        try {

            $bale = app(BaleBotService::class);

            $result = $bale->getWebhookInfo();

            if (
                $result &&
                ($result['ok'] ?? false)
            ) {

                $this->currentUrl =
                    $result['result']['url']
                    ?? null;

                if ($this->currentUrl) {

                    $this->status =
                        'Webhook فعال است';

                } else {

                    $this->status =
                        'Webhook تنظیم نشده است';
                }

            } else {

                $this->status =
                    'دریافت وضعیت Webhook ناموفق بود';
            }

        } catch (\Throwable $e) {

            $this->status =
                'خطا: ' . $e->getMessage();
        }
    }

    /**
     * تنظیم Webhook
     */
    public function setWebhook(): void
    {
        try {

            if (!$this->webhookUrl) {

                Notification::make()
                    ->title('آدرس Webhook وارد نشده است')
                    ->danger()
                    ->send();

                return;
            }

            $bale = app(BaleBotService::class);

            $result =
                $bale->setWebhook(
                    $this->webhookUrl
                );

            if (
                $result &&
                ($result['ok'] ?? false)
            ) {

                Notification::make()
                    ->title('Webhook با موفقیت تنظیم شد')
                    ->success()
                    ->send();

                $this->loadWebhookInfo();

                return;
            }

            Notification::make()
                ->title('تنظیم Webhook ناموفق بود')
                ->body(
                    $result['description']
                    ?? 'خطای نامشخص'
                )
                ->danger()
                ->send();

        } catch (\Throwable $e) {

            Notification::make()
                ->title('خطا در تنظیم Webhook')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * حذف Webhook
     */
    public function deleteWebhook(): void
    {
        try {

            $bale = app(BaleBotService::class);

            $result =
                $bale->deleteWebhook();

            if (
                $result &&
                ($result['ok'] ?? false)
            ) {

                Notification::make()
                    ->title('Webhook حذف شد')
                    ->success()
                    ->send();

                $this->loadWebhookInfo();

                return;
            }

            Notification::make()
                ->title('حذف Webhook ناموفق بود')
                ->danger()
                ->send();

        } catch (\Throwable $e) {

            Notification::make()
                ->title('خطا در حذف Webhook')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * تست ارسال پیام
     */
    public function sendTestMessage(): void
    {
        try {

            $chatId = env('BALE_NEWS_CHAT_ID');

            if (!$chatId) {

                Notification::make()
                    ->title('Chat ID تنظیم نشده است')
                    ->body(
                        'BALE_NEWS_CHAT_ID را در .env تنظیم کنید.'
                    )
                    ->warning()
                    ->send();

                return;
            }

            $bale = app(BaleBotService::class);

            $result = $bale->sendMessage(
                $chatId,
                "✅ تست ربات راصد\n\n"
                . "اتصال ربات بله با موفقیت انجام شد."
            );

            if (
                $result &&
                ($result['ok'] ?? false)
            ) {

                Notification::make()
                    ->title('پیام تست ارسال شد')
                    ->success()
                    ->send();

            } else {

                Notification::make()
                    ->title('ارسال پیام ناموفق بود')
                    ->body(
                        $result['description']
                        ?? 'خطای نامشخص'
                    )
                    ->danger()
                    ->send();
            }

        } catch (\Throwable $e) {

            Notification::make()
                ->title('خطا در ارسال پیام')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [

            Action::make('refresh')
                ->label('بروزرسانی وضعیت')
                ->icon('heroicon-o-arrow-path')
                ->action(
                    'loadWebhookInfo'
                ),

            Action::make('setWebhook')
                ->label('تنظیم Webhook')
                ->icon('heroicon-o-link')
                ->color('success')
                ->requiresConfirmation()
                ->action(
                    'setWebhook'
                ),

            Action::make('deleteWebhook')
                ->label('حذف Webhook')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(
                    'deleteWebhook'
                ),

            Action::make('testMessage')
                ->label('ارسال پیام تست')
                ->icon('heroicon-o-paper-airplane')
                ->action(
                    'sendTestMessage'
                ),
        ];
    }
}
