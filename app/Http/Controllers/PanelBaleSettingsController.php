<?php

namespace App\Http\Controllers;

use App\Services\BaleBotService;
use Illuminate\Http\Request;
use Throwable;

class PanelBaleSettingsController extends Controller
{
    public function index(BaleBotService $bale)
    {
        $webhookUrl = url('/bale/webhook');
        $currentUrl = null;
        $error = null;

        try {
            $response = $bale->getWebhookInfo();
            $currentUrl = data_get($response, 'result.url');
            if (!($response['ok'] ?? false)) $error = $response['description'] ?? 'دریافت وضعیت وب‌هوک ناموفق بود.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return view('panel.bale-settings', compact('webhookUrl', 'currentUrl', 'error'));
    }

    public function setWebhook(Request $request, BaleBotService $bale)
    {
        $data = $request->validate(['webhook_url' => ['required', 'url', 'starts_with:https://']]);
        $response = $bale->setWebhook($data['webhook_url']);

        return back()->with(($response['ok'] ?? false) ? 'success' : 'error',
            ($response['ok'] ?? false) ? 'وب‌هوک با موفقیت تنظیم شد.' : ($response['description'] ?? 'تنظیم وب‌هوک ناموفق بود.'));
    }

    public function deleteWebhook(BaleBotService $bale)
    {
        $response = $bale->deleteWebhook();

        return back()->with(($response['ok'] ?? false) ? 'success' : 'error',
            ($response['ok'] ?? false) ? 'وب‌هوک حذف شد.' : ($response['description'] ?? 'حذف وب‌هوک ناموفق بود.'));
    }

    public function testMessage(BaleBotService $bale)
    {
        $chatId = env('BALE_NEWS_CHAT_ID');
        if (!$chatId) return back()->with('error', 'شناسه‌ی مقصد در BALE_NEWS_CHAT_ID تنظیم نشده است.');

        $response = $bale->sendMessage($chatId, "✅ پیام آزمایشی رصد\n\nارتباط ربات بله برقرار است.");

        return back()->with(($response['ok'] ?? false) ? 'success' : 'error',
            ($response['ok'] ?? false) ? 'پیام آزمایشی ارسال شد.' : ($response['description'] ?? 'ارسال پیام آزمایشی ناموفق بود.'));
    }
}
