<?php

namespace App\Jobs;

use App\Models\BaleSubscriber;
use App\Models\SourceItem;
use App\Services\BaleBotService;
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
        BaleBotService $bale
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
                if ($keyboard) {

                    $result = $bale->sendWithKeyboard(
                        $subscriber->chat_id,
                        $text,
                        $keyboard
                    );

                } else {

                    /*
                     * اگر URL وجود نداشت،
                     * پیام بدون دکمه ارسال می‌شود.
                     */
                    $result = $bale->sendMessage(
                        $subscriber->chat_id,
                        $text
                    );
                }

                /*
                 * ثبت زمان آخرین ارسال موفق
                 */
                if (
                    $result &&
                    ($result['ok'] ?? false)
                ) {

                    $subscriber->update([
                        'last_sent_at' => now(),
                    ]);

                } else {

                    logger()->error(
                        'ارسال خبر به مشترک بله ناموفق بود',
                        [
                            'subscriber_id' =>
                                $subscriber->id,

                            'source_item_id' =>
                                $item->id,

                            'result' =>
                                $result,
                        ]
                    );
                }

            } catch (Throwable $e) {

                /*
                 * خطای یک مشترک نباید
                 * ارسال برای مشترک‌های دیگر را متوقف کند.
                 */
                logger()->error(
                    'خطا در ارسال خبر به مشترک بله',
                    [
                        'subscriber_id' =>
                            $subscriber->id,

                        'source_item_id' =>
                            $item->id,

                        'error' =>
                            $e->getMessage(),
                    ]
                );
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
