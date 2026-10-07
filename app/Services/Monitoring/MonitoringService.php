<?php

namespace App\Services\Monitoring;

use App\Jobs\SendNewsToBaleSubscribersJob;
use App\Models\Source;
use App\Models\SourceItem;
use App\Services\Monitoring\Readers\SourceReaderFactory;
use Throwable;

class MonitoringService
{
    public function __construct(
        protected SourceReaderFactory $readerFactory,
        protected KeywordMatcher $keywordMatcher,
        protected BlacklistMatcher $blacklistMatcher,
        protected CategoryClassifier $categoryClassifier,
        protected SimilarityService $similarityService,
        protected MonitoringErrorStore $errorStore,
    ) {}

    public function monitor(Source $source): array
    {
        $stats = [
            'source_id' => $source->id, 'source_name' => $source->name,
            'read' => 0, 'saved' => 0, 'duplicate' => 0,
            'blacklisted' => 0, 'no_keyword' => 0, 'reposts' => 0, 'failed' => 0,
        ];

        try {
            $reader = $this->readerFactory->make($source);
            $items = $reader->read($source);
            $stats['read'] = count($items);

            // پروفایل کانال ایتا را از خود صفحه ذخیره کن.
            if ($source->type === 'eitaa' && !empty($items[0]?->rawData['profile_image_url'])) {
                $source->profile_image_url = $items[0]->rawData['profile_image_url'];
                $source->save();
            }
            $lastExternalId = null;

            foreach ($items as $item) {
                $lastExternalId = $item->externalId;

                $exists = SourceItem::query()
                    ->where('source_id', $source->id)
                    ->where('external_id', $item->externalId)
                    ->exists();
                if ($exists) { $stats['duplicate']++; continue; }

                $content = trim(($item->title ?? '') . "\n\n" . ($item->content ?? ''));
                if ($this->blacklistMatcher->hasMatch($content)) { $stats['blacklisted']++; continue; }

                // The link itself is deliberately NOT part of keyword matching.
                $match = $this->keywordMatcher->match($content);
                if (!$match['keyword']) { $stats['no_keyword']++; continue; }

                $category = $this->categoryClassifier->classify($source, $content);
                $storedContent = $this->trimContentAroundKeyword($item->content ?? '', $match['keyword']->word);
                $repost = $this->similarityService->findRepost(
                    $item->title,
                    $item->content ?? '',
                    $category?->id
                );

                $sourceItem = SourceItem::create([
                    'source_id' => $source->id,
                    'external_id' => $item->externalId,
                    'title' => $item->title,
                    'url' => $item->url,
                    'featured_image_url' => $item->featuredImageUrl ?: ($item->rawData['featured_image_url'] ?? null),
                    'content' => $storedContent,
                    'matched_content' => $match['paragraph'],
                    'matched_keyword' => $match['keyword']->word,
                    'published_at' => $item->publishedAt?->setTimezone('Asia/Tehran'),
                    'raw_data' => $item->rawData,
                    'category_id' => $category?->id,
                    'duplicate_of_id' => $repost['item']->id ?? null,
                    'similarity_percent' => $repost['percent'] ?? null,
                    'is_repost' => (bool) $repost,
                ]);

                SendNewsToBaleSubscribersJob::dispatch($sourceItem->id);
                $stats['saved']++;
                if ($repost) $stats['reposts']++;
            }

            $source->last_item_id = $lastExternalId ?? $source->last_item_id;
            $source->last_read_at = now('Asia/Tehran');
            $source->save();

            return $stats;
        } catch (Throwable $e) {
            $stats['failed'] = 1;
            $this->errorStore->record($source, $e, null, [
                'source_type' => $source->type,
                'source_url' => $source->url,
            ]);
            throw $e;
        }
    }

    public function monitorAll(): array
    {
        $results = [];
        Source::query()->where('is_active', true)->orderBy('id')->each(function (Source $source) use (&$results) {
            try { $results[] = $this->monitor($source); }
            catch (Throwable $e) { $results[] = ['source_id'=>$source->id,'source_name'=>$source->name,'failed'=>1,'error'=>$e->getMessage()]; }
        });
        return $results;
    }

    protected function trimContentAroundKeyword(string $content, string $keyword, int $maxLength = 10000, int $before = 3500): string
    {
        $content = trim($content);
        $keyword = trim($keyword);
        if ($content === '' || mb_strlen($content) <= $maxLength) return $content;

        $position = mb_stripos($this->normalizeForSearch($content), $this->normalizeForSearch($keyword));
        if ($position === false) return trim(mb_substr($content, 0, $maxLength)) . "\n\n[...]";

        $start = max(0, $position - $before);
        if ($start + $maxLength > mb_strlen($content)) $start = max(0, mb_strlen($content) - $maxLength);
        $result = mb_substr($content, $start, $maxLength);
        if ($start > 0) $result = "[...]\n\n" . $result;
        if (($start + $maxLength) < mb_strlen($content)) $result .= "\n\n[...]";
        return trim($result);
    }

    protected function normalizeForSearch(string $text): string
    {
        return str_replace(['ي','ى','ك'], ['ی','ی','ک'], mb_strtolower($text));
    }
}
