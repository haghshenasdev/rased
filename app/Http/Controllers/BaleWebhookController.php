<?php

namespace App\Http\Controllers;

use App\Models\BaleSubscriber;
use App\Services\BaleBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BaleWebhookController extends Controller
{
    public function handle(
        Request $request,
        BaleBotService $bale
    ) {

        $update = $request->all();

        Log::info(
            'Bale webhook',
            $update
        );

        /*
         * پیام دریافتی
         */
        $message =
            $update['message']
            ?? null;

        if (!$message) {
            return response()->json([
                'ok' => true,
            ]);
        }

        /*
         * Chat ID فرد
         */
        $chatId =
            $message['chat']['id']
            ?? null;

        /*
         * متن پیام
         */
        $text =
            trim(
                $message['text']
                ?? ''
            );

        if (!$chatId || !$text) {
            return response()->json([
                'ok' => true,
            ]);
        }

        /*
         * جستجوی Token
         */
        $subscriber =
            BaleSubscriber::query()
                ->where('token', $text)
                ->where('is_active', true)
                ->first();

        /*
         * Token پیدا نشد
         */
        if (!$subscriber) {

            $bale->sendMessage(
                $chatId,
                "❌ این کد اتصال معتبر نیست.\n\n"
                . "لطفاً توکن را دقیقاً همان‌طور که دریافت کرده‌اید ارسال کنید."
            );

            return response()->json([
                'ok' => true,
            ]);
        }

        /*
         * اتصال فرد
         */
        $subscriber->update([
            'chat_id' => (string) $chatId,
            'connected_at' => now(),
        ]);

        /*
         * پیام موفقیت
         */
        $bale->sendMessage(
            $chatId,
            "✅ اتصال با موفقیت انجام شد.\n\n"
            . "از این لحظه اخبار جدید را برای شما ارسال می‌کنیم."
        );

        return response()->json([
            'ok' => true,
        ]);
    }
}
