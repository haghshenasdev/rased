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
use Morilog\Jalali\Jalalian;
use Throwable;

class SendSelectedNewsToBaleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param array<int> $sourceItemIds
     * @param array<int> $subscriberIds
     */
    public function __construct(
        public array $sourceItemIds,
        public array $subscriberIds,
    ) {
    }

    public function handle(
        BaleBotService $bale,
        BaleSendLogStore $logStore
    ): void {

        $items = SourceItem::query()
            ->with('source')
            ->whereIn('id', $this->sourceItemIds)
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $subscribers = BaleSubscriber::query()
            ->whereIn('id', $this->subscriberIds)
            ->where('is_active', true)
            ->whereNotNull('chat_id')
            ->get();

        if ($subscribers->isEmpty()) {
            return;
        }

        /*
         * برای هر مشترک، تمام اخبار انتخاب‌شده
         * ارسال می‌شوند.
         */
        foreach ($subscribers as $subscriber) {

            foreach ($items as $item) {

                try {

                    $text = $this->buildMessage($item);

                    /*
                     * اگر خبر URL داشته باشد،
                     * دکمه مشاهده خبر ساخته می‌شود.
                     */
                    if (
                        filled($item->url)
                    ) {

                        $keyboard =
                            $bale->inlineKeyboard([
                                [
                                    $bale->urlButton(
                                        '🔗 مشاهده خبر',
                                        $item->url
                                    ),
                                ],
                            ]);

                        $result = $item->featured_image_url
                            ? $bale->sendPhotoByUrl(
                                $subscriber->chat_id,
                                $item->featured_image_url,
                                $text,
                                $keyboard
                            )
                            : $bale->sendWithKeyboard(
                                $subscriber->chat_id,
                                $text,
                                $keyboard
                            );

                    } else {

                        $result =
                            $bale->sendMessage(
                                $subscriber->chat_id,
                                $text
                            );
                    }

                    /*
                     * ارسال موفق
                     */
                    $ctx = [
                        'chat_id' => (string) $subscriber->chat_id,
                        'subscriber_id' => $subscriber->id,
                        'has_image' => filled($item->featured_image_url),
                        'featured_image_url' => $item->featured_image_url,
                        'http_status' => $result['_http_status'] ?? null,
                        'api_result' => $result,
                        'mode' => 'manual_selected',
                    ];

                    if ($result && ($result['ok'] ?? false) === true) {
                        $subscriber->update(['last_sent_at' => now()]);
                        $logStore->record($item, 'success', 'ارسال دستی خبر به بله موفق بود.', $ctx);
                    } else {
                        $logStore->record($item, 'failed', 'ارسال دستی خبر به بله ناموفق بود: ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $ctx);
                    }

                } catch (Throwable $e) {

                    /*
                     * خطای یک مشترک نباید
                     * ارسال بقیه را متوقف کند.
                     */
                    $logStore->exception($item, $e, [
                        'subscriber_id' => $subscriber->id,
                        'chat_id' => (string) $subscriber->chat_id,
                        'mode' => 'manual_selected',
                    ]);
                }
            }
        }
    }

    /**
     * ساخت متن پیام بله
     */
    private function buildMessage(
        SourceItem $item
    ): string {

        $source =
            $item->source?->name
            ?? 'منبع نامشخص';

        $text = '';

        /*
         * عنوان
         */
        $text .=
            "📰 {$item->title}\n";

        $text .=
            "━━━━━━━━━━━━━━━━━━\n";

        /*
         * منبع
         */
        $text .=
            "📡 منبع: {$source}\n";

        /*
         * تاریخ انتشار
         */
        if ($item->published_at) {

            try {

                $date =
                    Jalalian::fromCarbon(
                        $item->published_at
                    )->format(
                        'Y/m/d H:i'
                    );

                $text .=
                    "🕐 تاریخ انتشار: {$date}\n";

            } catch (Throwable) {

                $text .=
                    "🕐 تاریخ انتشار: "
                    . $item->published_at->format(
                        'Y/m/d H:i'
                    )
                    . "\n";
            }
        }

        /*
         * کلمه کلیدی
         */
        if (
            filled($item->matched_keyword)
        ) {

            $text .=
                "🔎 کلمه کلیدی: "
                . $item->matched_keyword
                . "\n";
        }

        /*
         * بخش مرتبط
         */
        if (
            filled($item->matched_content)
        ) {

            $text .= "\n";

            $text .=
                "📌 بخش مرتبط:\n";

            $text .=
                "──────────────\n";

            $content =
                trim($item->matched_content);

            /*
             * محدود کردن متن برای جلوگیری
             * از پیام بیش از حد طولانی
             */
            if (
                mb_strlen($content) > 1500
            ) {

                $content =
                    mb_substr(
                        $content,
                        0,
                        1500
                    ) . '...';
            }

            $text .= $content;

            $text .= "\n";
        }

        /*
         * فوتر
         */
        $text .= "\n";

        $text .=
            "━━━━━━━━━━━━━━━━━━\n";

        $text .=
            "🤖 ارسال شده توسط «راصد»";

        return $text;
    }

    public function failed(
        Throwable $exception
    ): void {

        $firstItem = SourceItem::query()->with('source')->find($this->sourceItemIds[0] ?? 0);
        app(BaleSendLogStore::class)->exception($firstItem, $exception, [
            'mode' => 'manual_selected',
            'source_item_ids' => $this->sourceItemIds,
            'subscriber_ids' => $this->subscriberIds,
            'job_failed' => true,
        ]);
    }
}
