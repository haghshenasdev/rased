<?php

namespace App\Services\Monitoring;

use App\Jobs\SendNewsToBaleSubscribersJob;
use App\Models\Source;
use App\Models\SourceItem;
use App\Services\Monitoring\Readers\SourceReaderFactory;
use Throwable;
use Illuminate\Support\Facades\Http;

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
                $this->storeSourceProfileImage($source, (string) $items[0]->rawData['profile_image_url']);
            }
            $lastExternalId = null;
            $lastItemUrl = null;

            foreach ($items as $item) {
                // GUIDهای RSS گاهی بسیار طولانی‌اند؛ کلید پایدار و محدود برای ایندکس DB بساز.
                $externalId = $this->safeExternalId((string) $item->externalId);
                $lastExternalId = $externalId;

                // لینک آخرین آیتمی که در این نوبت خوانده می‌شود را مستقل از کلمه کلیدی نگه دار.
                // بنابراین خبر تکراری، بلک‌لیست‌شده یا بدون کلمه کلیدی هم لینک خود را ثبت می‌کند.
                $lastItemUrl = !empty($item->url) ? (string) $item->url : $lastItemUrl;
                $exists = SourceItem::query()
                    ->where('source_id', $source->id)
                    ->where('external_id', $externalId)
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
                    'external_id' => $externalId,
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
            $source->last_item_url = $lastItemUrl ?? $source->last_item_url;
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


    /** ذخیره محلی تصویر پروفایل در public disk تا به دسترسی مستقیم میزبان تصویر وابسته نباشد. */
    protected function storeSourceProfileImage(Source $source, string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return;
        }
        try {
            $response = Http::connectTimeout(4)->timeout(8)->withHeaders(['User-Agent' => 'RasedNewsMonitor/1.0', 'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/*;q=0.8'])->get($url);
            if (!$response->successful() || strlen($response->body()) > 3 * 1024 * 1024) {
                $source->profile_image_url = $url;
                $source->save();
                return;
            }
            $mime = strtolower((string) $response->header('Content-Type'));
            if (!str_starts_with($mime, 'image/')) {
                $source->profile_image_url = $url;
                $source->save();
                return;
            }
            $extension = str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'webp') ? 'webp' : (str_contains($mime, 'gif') ? 'gif' : 'jpg'));
            $relativePath = 'source-profiles/' . $source->id . '.' . $extension;
            $absoluteDirectory = public_path('source-profiles');
            if (!is_dir($absoluteDirectory)) {
                mkdir($absoluteDirectory, 0755, true);
            }
            file_put_contents(public_path($relativePath), $response->body());
            $path = $relativePath;
            $source->profile_image_path = $path;
            $source->profile_image_url = null;
            $source->save();
        } catch (Throwable $e) {
            // نگه داشتن URL خارجی به عنوان fallback؛ شکست تصویر نباید کل اسکن را متوقف کند.
            $source->profile_image_url = $url;
            $source->save();
        }
    }

    protected function safeExternalId(string $value): string
    {
        $value = trim($value);
        if ($value === '') return hash('sha256', 'empty');
        return mb_strlen($value) <= 180 ? $value : 'sha256:' . hash('sha256', $value);
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
