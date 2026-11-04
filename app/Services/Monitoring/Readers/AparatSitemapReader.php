<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * دریافت آخرین ویدئوهای آپارات از Video Sitemap.
 * عنوان، توضیحات، برچسب‌ها، منتشرکننده و تصویر از خود XML خوانده می‌شوند؛
 * بنابراین برای هر ویدئو نیازی به باز کردن صفحه آن نیست.
 */
class AparatSitemapReader implements SourceReaderInterface
{
    private const DEFAULT_SITEMAP = 'https://www.aparat.com/sitemap_1.xml';
    private const USER_AGENT = 'Mozilla/5.0 (compatible; RasedNewsMonitor/1.0)';

    public function read(Source $source): array
    {
        $settings = is_array($source->settings) ? $source->settings : [];
        $sitemapUrl = trim((string) ($source->url ?: self::DEFAULT_SITEMAP));
        if (!filter_var($sitemapUrl, FILTER_VALIDATE_URL)
            || !in_array(strtolower((string) parse_url($sitemapUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new RuntimeException('آدرس سایت‌مپ آپارات معتبر نیست.');
        }

        $maxItems = min(1000, max(1, (int) ($settings['max_items'] ?? 200)));
        $entries = $this->readVideoEntries($sitemapUrl);
        if (!$entries) {
            throw new RuntimeException('در سایت‌مپ آپارات هیچ ویدئویی پیدا نشد.');
        }

        $cursorUrl = trim((string) ($settings['aparat_sitemap_cursor_url'] ?? ''));
        $seen = $settings['aparat_sitemap_seen_urls'] ?? [];
        if (!is_array($seen)) $seen = [];
        $seenSet = array_fill_keys(array_map('strval', $seen), true);

        // اگر نشانگر قبلی دیگر در سایت‌مپ فعلی وجود ندارد، پیمایش تازه را از ابتدا آغاز کن.
        if ($cursorUrl !== '' && !isset($entries[$cursorUrl])) {
            $cursorUrl = '';
            $seenSet = [];
        }

        $results = [];
        $attempted = 0;
        $limitReached = false;
        $reachedCursor = false;

        foreach ($entries as $url => $entry) {
            if ($cursorUrl !== '' && $url === $cursorUrl) {
                $reachedCursor = true;
                break;
            }
            if (isset($seenSet[$url])) continue;

            if ($attempted >= $maxItems) {
                $limitReached = true;
                break;
            }
            $attempted++;
            $seenSet[$url] = true;

            $title = $this->cleanText($entry['title']);
            if ($title === '') continue;

            $description = $this->cleanText($entry['description']);
            $tags = $this->cleanText($entry['tags']);
            $uploader = $this->cleanText($entry['uploader']);
            $contentParts = array_filter([$description, $tags !== '' ? 'برچسب‌ها: ' . $tags : '']);
            $content = implode("\n\n", $contentParts);

            $results[] = new SourceItemData(
                externalId: 'aparat:' . $this->videoId($url),
                title: $title,
                url: $url,
                content: $content !== '' ? $content : null,
                publishedAt: $this->parseDate($entry['published_at'] ?: $entry['lastmod']),
                featuredImageUrl: $entry['thumbnail'] ?: null,
                rawData: [
                    'source' => 'aparat_sitemap',
                    'description' => $description,
                    'tags' => $tags,
                    'uploader' => $uploader,
                    'uploader_url' => $entry['uploader_url'],
                    'thumbnail_url' => $entry['thumbnail'],
                    'player_url' => $entry['player'],
                    'duration' => $entry['duration'],
                    'publication_date' => $entry['published_at'],
                    'sitemap_lastmod' => $entry['lastmod'],
                ],
            );
        }

        // نشانگر فقط وقتی جلو می‌رود که همه آیتم‌های جدیدِ این سایت‌مپ پیمایش شده باشند.
        $scanComplete = !$limitReached && ($reachedCursor || $cursorUrl === '');
        if ($scanComplete) {
            $settings['aparat_sitemap_cursor_url'] = (string) array_key_first($entries);
            $settings['aparat_sitemap_seen_urls'] = [];
        } else {
            $settings['aparat_sitemap_cursor_url'] = $cursorUrl;
            $settings['aparat_sitemap_seen_urls'] = array_values(array_keys($seenSet));
        }
        $settings['aparat_sitemap_cursor_mode'] = 'newest_first_v1';
        $source->settings = $settings;

        return $results;
    }

    /**
     * @return array<string,array{title:string,description:string,tags:string,uploader:string,uploader_url:?string,thumbnail:?string,player:?string,duration:?string,published_at:?string,lastmod:?string}>
     */
    private function readVideoEntries(string $url): array
    {
        $cacheKey = 'rased:aparat:sitemap:v1:' . sha1($url);
        $entries = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($url) {
            $response = Http::connectTimeout(8)->timeout(35)->retry(2, 1000)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.7',
                ])->get($url);

            if (!$response->successful()) {
                throw new RuntimeException('دریافت سایت‌مپ آپارات ناموفق بود؛ HTTP ' . $response->status());
            }
            $body = trim($response->body());
            if ($body === '' || strlen($body) > 50 * 1024 * 1024) {
                throw new RuntimeException('پاسخ سایت‌مپ آپارات خالی یا بیش از حد بزرگ است.');
            }

            // گاهی در توضیحات ویدئو نویسه‌های کنترلی نامعتبر XML (مثل VT) وجود دارد.
            // این نویسه‌ها طبق XML 1.0 مجاز نیستند؛ پیش از parse حذف می‌شوند.
            $body = preg_replace(
                '/[^\\x{9}\\x{A}\\x{D}\\x{20}-\\x{D7FF}\\x{E000}-\\x{FFFD}\\x{10000}-\\x{10FFFF}]/u',
                '',
                $body
            ) ?? $body;

            $previous = libxml_use_internal_errors(true);
            try {
                $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (!$xml) throw new RuntimeException('ساختار XML سایت‌مپ آپارات قابل خواندن نیست.');

            $result = [];
            $nodes = $xml->xpath('//*[local-name()="url"]') ?: [];
            foreach ($nodes as $node) {
                $loc = $this->nodeText($node, './*[local-name()="loc"]');
                if ($loc === '' || !$this->isAparatVideoUrl($loc)) continue;

                $video = $node->xpath('./*[local-name()="video"]');
                if (!$video) continue;
                $video = $video[0];

                $uploaderNodes = $video->xpath('./*[local-name()="uploader"]');
                $uploaderNode = $uploaderNodes[0] ?? null;
                $uploaderUrl = null;
                if ($uploaderNode) {
                    $attributes = $uploaderNode->attributes();
                    $uploaderUrl = trim((string) ($attributes['info'] ?? '')) ?: null;
                }

                $result[$loc] = [
                    'title' => $this->nodeText($video, './*[local-name()="title"]'),
                    'description' => $this->nodeText($video, './*[local-name()="description"]'),
                    'tags' => $this->nodeText($video, './*[local-name()="tag"]'),
                    'uploader' => $uploaderNode ? trim((string) $uploaderNode) : '',
                    'uploader_url' => $uploaderUrl,
                    'thumbnail' => $this->nullable($this->nodeText($video, './*[local-name()="thumbnail_loc"]')),
                    'player' => $this->nullable($this->nodeText($video, './*[local-name()="player_loc"]')),
                    'duration' => $this->nullable($this->nodeText($video, './*[local-name()="duration"]')),
                    'published_at' => $this->nullable($this->nodeText($video, './*[local-name()="publication_date"]')),
                    'lastmod' => $this->nullable($this->nodeText($node, './*[local-name()="lastmod"]')),
                ];
            }
            return $result;
        });

        if (!$entries) Cache::forget($cacheKey);
        return $entries;
    }

    private function nodeText(\SimpleXMLElement $node, string $xpath): string
    {
        $values = $node->xpath($xpath);
        return isset($values[0]) ? trim((string) $values[0]) : '';
    }

    private function isAparatVideoUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
            && in_array(strtolower($parts['host'] ?? ''), ['aparat.com', 'www.aparat.com'], true)
            && preg_match('~^/v/[a-z0-9]+/?$~i', (string) ($parts['path'] ?? '')) === 1;
    }

    private function videoId(string $url): string
    {
        return trim((string) basename((string) parse_url($url, PHP_URL_PATH)));
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00A0}\s]+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function nullable(string $value): ?string
    {
        return trim($value) !== '' ? trim($value) : null;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) return null;
        try {
            return Carbon::parse($value)->setTimezone('Asia/Tehran');
        } catch (Throwable) {
            return null;
        }
    }
}
