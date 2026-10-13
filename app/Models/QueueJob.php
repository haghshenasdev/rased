<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    protected $table = 'jobs';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * شرح قابل‌خواندن Job از روی payload موجود در جدول jobs.
     * هیچ فیلد یا جدول جدیدی ایجاد نمی‌شود.
     */
    public function description(): string
    {
        $payload = json_decode((string) $this->payload, true);

        if (!is_array($payload)) {
            return 'وظیفه پردازشی (اطلاعات قابل خواندن نیست)';
        }

        $class = (string) (
            $payload['displayName']
            ?? $payload['job']
            ?? ''
        );

        $command = (string) data_get($payload, 'data.command', '');

        if (str_contains($class, 'CheckSourceJob')) {
            $sourceId = $this->serializedInteger($command, 'sourceId');

            if ($sourceId !== null) {
                $source = Source::query()->find($sourceId);

                return $source
                    ? 'بررسی منبع خبری: ' . $source->name
                    : 'بررسی منبع خبری شماره ' . $sourceId;
            }

            return 'بررسی و دریافت اخبار منابع';
        }

        if (str_contains($class, 'SendSelectedNewsToBaleJob')) {
            $itemsCount = $this->serializedArrayCount($command, 'sourceItemIds');
            $subscribersCount = $this->serializedArrayCount($command, 'subscriberIds');

            $details = [];

            if ($itemsCount !== null) {
                $details[] = $itemsCount . ' خبر';
            }

            if ($subscribersCount !== null) {
                $details[] = $subscribersCount . ' مشترک';
            }

            return 'ارسال خبرهای انتخاب‌شده به مشترکان بله'
                . ($details ? ' (' . implode('، ', $details) . ')' : '');
        }

        if (str_contains($class, 'SendNewsToBaleSubscribersJob')) {
            return $this->newsSendingDescription(
                $command,
                'ارسال خبر به مشترکان بله'
            );
        }

        if (str_contains($class, 'SendNewsToBaleJob')) {
            return $this->newsSendingDescription(
                $command,
                'ارسال خودکار خبر به بله'
            );
        }

        $shortName = class_basename($class);

        return match ($shortName) {
            'CallQueuedHandler' => 'اجرای وظیفه صف',
            '' => 'وظیفه پردازشی',
            default => 'پردازش: ' . $shortName,
        };
    }

    private function newsSendingDescription(
        string $command,
        string $prefix
    ): string {
        $itemId = $this->serializedInteger($command, 'sourceItemId');

        if ($itemId === null) {
            return $prefix;
        }

        $item = SourceItem::query()
            ->with('source')
            ->find($itemId);

        if (!$item) {
            return $prefix . ' (خبر شماره ' . $itemId . ')';
        }

        $title = trim((string) $item->title);
        if (mb_strlen($title) > 100) {
            $title = mb_substr($title, 0, 100) . '…';
        }

        $sourceName = $item->source?->name;

        return $prefix . ': ' . ($title !== '' ? $title : 'خبر شماره ' . $itemId)
            . ($sourceName ? ' — منبع: ' . $sourceName : '');
    }

    private function serializedInteger(string $command, string $property): ?int
    {
        $quoted = preg_quote($property, '/');

        if (preg_match('/s:\d+:"' . $quoted . '";i:(\d+);/', $command, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function serializedArrayCount(string $command, string $property): ?int
    {
        $quoted = preg_quote($property, '/');

        if (preg_match('/s:\d+:"' . $quoted . '";a:(\d+):\{/', $command, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
