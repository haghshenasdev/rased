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

class SendNewsToBaleSubscribersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

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

        $subscribers =
            BaleSubscriber::query()
                ->where('is_active', true)
                ->whereNotNull('chat_id')
                ->get();

        $text = $this->buildMessage($item);

        foreach ($subscribers as $subscriber) {

            try {

                $result =
                    $bale->sendMessage(
                        $subscriber->chat_id,
                        $text
                    );

                if (
                    $result &&
                    ($result['ok'] ?? false)
                ) {

                    $subscriber->update([
                        'last_sent_at' => now(),
                    ]);
                }

            } catch (\Throwable $e) {

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

    private function buildMessage(
        SourceItem $item
    ): string {

        $source =
            $item->source?->name
            ?? 'منبع نامشخص';

        $text =
            "📰 {$item->title}\n\n";

        $text .=
            "📡 منبع: {$source}\n";

        if ($item->matched_keyword) {

            $text .=
                "🔎 کلمه کلیدی: "
                . $item->matched_keyword
                . "\n";
        }

        if ($item->matched_content) {

            $text .=
                "\n📌 بخش مرتبط:\n"
                . $item->matched_content
                . "\n";
        }

        if ($item->url) {

            $text .=
                "\n🔗 {$item->url}";
        }

        return $text;
    }
}
