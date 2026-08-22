<?php

namespace App\Jobs;

use App\Models\SourceItem;
use App\Services\BaleBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendNewsToBaleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

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

        $chatId = env('BALE_NEWS_CHAT_ID');

        if (!$chatId) {
            throw new \Exception(
                'BALE_NEWS_CHAT_ID تنظیم نشده است.'
            );
        }

        $text = $this->buildMessage($item);

        $result = $bale->sendMessage(
            $chatId,
            $text
        );

        /*
         * اگر API بله خطا داد،
         * Job را Failed کن تا دوباره تلاش شود.
         */
        if (!$result || ($result['ok'] ?? false) !== true) {

            throw new \Exception(
                'خطا در ارسال خبر به بله: ' .
                json_encode(
                    $result,
                    JSON_UNESCAPED_UNICODE
                )
            );
        }
    }

    private function buildMessage(
        SourceItem $item
    ): string {

        $source = $item->source?->name
            ?? 'منبع نامشخص';

        $text = '';

        $text .= "📰 {$item->title}\n\n";

        $text .= "📡 منبع: {$source}\n";

        if ($item->published_at) {
            $text .= "🕐 تاریخ: "
                . $item->published_at->format('Y/m/d H:i')
                . "\n";
        }

        if ($item->matched_keyword) {

            $text .= "\n";
            $text .= "🔎 کلمه کلیدی: ";
            $text .= $item->matched_keyword;
            $text .= "\n";
        }

        if ($item->matched_content) {

            $text .= "\n";
            $text .= "📌 بخش مرتبط:\n";
            $text .= $item->matched_content;
            $text .= "\n";
        }

        if ($item->url) {

            $text .= "\n";
            $text .= "🔗 لینک خبر:\n";
            $text .= $item->url;
        }

        $text .= "\n\n";
        $text .= "──────\n";
        $text .= "🤖 ارسال شده توسط راصد";

        return $text;
    }

    public function failed(
        Throwable $exception
    ): void {

        logger()->error(
            'ارسال خبر به بله ناموفق بود',
            [
                'source_item_id' =>
                    $this->sourceItemId,

                'error' =>
                    $exception->getMessage(),
            ]
        );
    }
}
