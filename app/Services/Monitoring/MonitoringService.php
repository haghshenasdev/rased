<?php

namespace App\Services\Monitoring;

use App\Jobs\SendNewsToBaleJob;
use App\Models\Source;
use App\Models\SourceItem;
use App\Services\Monitoring\Readers\SourceReaderFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class MonitoringService
{
    public function __construct(
        protected SourceReaderFactory $readerFactory,
        protected KeywordMatcher $keywordMatcher,
        protected BlacklistMatcher $blacklistMatcher,
    ) {
    }

    /**
     * اجرای مانیتورینگ یک منبع
     */
    public function monitor(Source $source): array
    {
        $startedAt = now();

        $stats = [
            'source_id' => $source->id,
            'source_name' => $source->name,
            'read' => 0,
            'saved' => 0,
            'duplicate' => 0,
            'blacklisted' => 0,
            'no_keyword' => 0,
            'failed' => 0,
        ];

        try {

            /*
             * Reader مناسب منبع
             */
            $reader = $this->readerFactory->make($source);

            /*
             * دریافت مطالب جدید
             */
            $items = $reader->read($source);

            $stats['read'] = count($items);

            /*
             * آخرین ID دریافت‌شده
             */
            $lastExternalId = null;

            foreach ($items as $item) {

                $lastExternalId = $item->externalId;

                /*
                 * Duplicate check
                 */
                $exists = SourceItem::query()
                    ->where('source_id', $source->id)
                    ->where(
                        'external_id',
                        $item->externalId
                    )
                    ->exists();

                if ($exists) {
                    $stats['duplicate']++;

                    continue;
                }

                $content = trim(
                    ($item->title ?? '') .
                    "\n\n" .
                    ($item->content ?? '')
                );

                /*
                 * Blacklist
                 */
                if (
                    $this->blacklistMatcher
                        ->hasMatch($content)
                ) {
                    $stats['blacklisted']++;

                    continue;
                }

                /*
                 * Keyword
                 */
                $match = $this->keywordMatcher->match(
                    $content
                );

                if (!$match['keyword']) {
                    $stats['no_keyword']++;

                    continue;
                }

                /*
                 * ذخیره
                 */
                $sourceItem = SourceItem::create([
                    'source_id' => $source->id,

                    'external_id' => $item->externalId,

                    'title' => $item->title,

                    'url' => $item->url,

                    'content' => $item->content,

                    'matched_content' =>
                        $match['paragraph'],

                    'matched_keyword' =>
                        $match['keyword']->word,

                    'published_at' =>
                        $item->publishedAt,

                    'raw_data' =>
                        $item->rawData,
                ]);

                /*
 * ارسال خبر جدید به بله
 */
                SendNewsToBaleJob::dispatch(
                    $sourceItem->id
                );

                $stats['saved']++;
            }

            /*
             * فقط وقتی Reader بدون خطا اجرا شده،
             * وضعیت منبع را به‌روز می‌کنیم.
             */
            if ($lastExternalId !== null) {

                $source->last_item_id =
                    $lastExternalId;

                $source->last_read_at =
                    now();

                $source->save();
            } else {

                /*
                 * حتی اگر مطلب جدیدی نبوده،
                 * زمان آخرین بررسی را ثبت کن.
                 */
                $source->last_read_at =
                    now();

                $source->save();
            }

            return $stats;

        } catch (Throwable $e) {

            $stats['failed'] = 1;

            throw $e;
        }
    }

    /**
     * اجرای تمام منابع فعال
     */
    public function monitorAll(): array
    {
        $results = [];

        Source::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (Source $source) use (&$results) {

                try {

                    $results[] =
                        $this->monitor($source);

                } catch (Throwable $e) {

                    $results[] = [
                        'source_id' =>
                            $source->id,

                        'source_name' =>
                            $source->name,

                        'failed' => 1,

                        'error' =>
                            $e->getMessage(),
                    ];
                }
            });

        return $results;
    }

}
