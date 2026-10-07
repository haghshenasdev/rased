<?php

namespace App\Jobs;

use App\Models\BaleSubscriber;
use App\Models\SourceItem;
use App\Services\BaleBotService;
use App\Services\BaleSendLogStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendNewsToBaleSubscribersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $sourceItemId
    ) {
    }

    public function handle(
        BaleBotService $bale,
        BaleSendLogStore $logStore
    ): void {

        $item = SourceItem::query()
            ->with('source')
            ->find($this->sourceItemId);

        if (!$item) {
            return;
        }

        $subscribers = BaleSubscriber::query()
            ->where('is_active', true)
            ->whereNotNull('chat_id')
            ->get();

        $text = $this->buildMessage($item);

        /*
         * ساخت دکمه مشاهده خبر
         */
        $keyboard = null;

        if ($item->url) {

            $keyboard = $bale->inlineKeyboard([
                [
                    $bale->urlButton(
                        '🔗 مشاهده خبر',
                        $item->url
                    ),
                ],
            ]);
        }

        foreach ($subscribers as $subscriber) {

            try {

                /*
                 * اگر لینک خبر وجود داشته باشد،
                 * پیام همراه با Inline Keyboard ارسال می‌شود.
                 */
                if ($item->featured_image_url) {
                    $result = $bale->sendPhotoByUrl(
                        $subscriber->chat_id,
                        $item->featured_image_url,
                        $text,
                        $keyboard
                    );
                } elseif ($keyboard) {
                    $result = $bale->sendWithKeyboard(
                        $subscriber->chat_id,
                        $text,
                        $keyboard
                    );
                } else {
                    $result = $bale->sendMessage($subscriber->chat_id, $text);
                }

                /*
                 * ثبت زمان آخرین ارسال موفق
                 */
                $ctx = [
                    'chat_id' => (string) $subscriber->chat_id,
                    'subscriber_id' => $subscriber->id,
                    'has_image' => filled($item->featured_image_url),
                    'featured_image_url' => $item->featured_image_url,
                    'http_status' => $result['_http_status'] ?? null,
                    'api_result' => $result,
                    'mode' => 'automatic_subscribers',
                ];

                if ($result && ($result['ok'] ?? false)) {
                    $subscriber->update(['last_sent_at' => now()]);
                    $logStore->record($item, 'success', 'ارسال خودکار به مشترک بله موفق بود.', $ctx);
                } else {
                    $logStore->record($item, 'failed', 'ارسال خودکار به مشترک بله ناموفق بود: ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $ctx);
                }

            } catch (Throwable $e) {

                /*
                 * خطای یک مشترک نباید
                 * ارسال برای مشترک‌های دیگر را متوقف کند.
                 */
                $logStore->exception($item, $e, [
                    'subscriber_id' => $subscriber->id,
                    'chat_id' => (string) $subscriber->chat_id,
                    'mode' => 'automatic_subscribers',
                ]);
            }
        }
    }

    /**
     * ساخت متن پیام خبر
     */
    private function buildMessage(
        SourceItem $item
    ): string {

        $source =
            $item->source?->name
            ?? 'منبع نامشخص';

        $text = '';

        /*
         * عنوان خبر
         */
        $text .= "📰 {$item->title}\n";

        $text .= "━━━━━━━━━━━━━━━━━━\n";

        /*
         * اطلاعات خبر
         */
        $text .= "📡 منبع: {$source}\n";

        if ($item->published_at) {

            $text .=
                "🕐 تاریخ انتشار: "
                . $item->published_at->format(
                    'Y/m/d H:i'
                )
                . "\n";
        }

        /*
         * کلمه کلیدی
         */
        if ($item->matched_keyword) {

            $text .=
                "🔎 کلمه کلیدی: "
                . $item->matched_keyword
                . "\n";
        }

        /*
         * بخش مرتبط خبر
         */
        if ($item->matched_content) {

            $text .= "\n";
            $text .= "📌 بخش مرتبط:\n";
            $text .= "──────────────\n";
            $text .= $item->matched_content;
            $text .= "\n";
        }

        /*
         * فوتر
         */
        $text .= "\n";
        $text .= "━━━━━━━━━━━━━━━━━━\n";
        $text .= "🤖 رصد شده توسط «راصد»";

        return $text;
    }
}
