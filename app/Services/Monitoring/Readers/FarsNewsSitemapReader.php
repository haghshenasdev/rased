<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * خواندن اخبار فارس از نقشه سایت و استخراج فقط عنوان و توضیحات HTML.
 * متن بدنه خبر عمداً دریافت یا ذخیره نمی‌شود.
 */
class FarsNewsSitemapReader implements SourceReaderInterface
{
    private const DEFAULT_SITEMAP = 'https://farsnews.ir/sitemaps/contents/index';
    private const USER_AGENT = 'RasedNewsMonitor/1.0 (+https://rased.hajideligani.ir)';

    public function read(Source $source): array
    {
        $settings = is_array($source->settings) ? $source->settings : [];
        $maxItems = min(300, max(1, (int) ($settings['max_items'] ?? 100)));
        $lookbackDays = min(14, max(1, (int) ($settings['lookback_days'] ?? 2)));
        $today = now('Asia/Tehran')->startOfDay();
        $fromDate = $today->copy()->subDays($lookbackDays - 1);

        $dailySitemaps = [];
        for ($year = (int) $fromDate->format('Y'); $year <= (int) $today->format('Y'); $year++) {
            $yearUrl = $this->yearIndexUrl((string) ($source->url ?: self::DEFAULT_SITEMAP . $today->format('Y') . '.xml'), $year);
            foreach ($this->readYearIndex($yearUrl) as $entry) {
                if (!$entry['url']) {
                    continue;
                }
                if (preg_match('~/contents/(\d{4})/(\d{2})/(\d{2})/~', $entry['url'], $m)) {
                    try {
                        $date = Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3], 'Asia/Tehran')->startOfDay();
                    } catch (Throwable) {
                        continue;
                    }
                    if ($date->betweenIncluded($fromDate, $today)) {
                        $dailySitemaps[$entry['url']] = $date->timestamp;
                    }
                }
            }
        }

        // برخی نقشه‌های سالانه ممکن است موقتاً ناقص باشند؛ حداقل نقشه امروز را امتحان می‌کنیم.
        if (!$dailySitemaps) {
            $dailySitemaps[$this->dailySitemapUrl($today)] = $today->timestamp;
        }
        arsort($dailySitemaps, SORT_NUMERIC);

        $articleUrls = [];
        foreach (array_keys($dailySitemaps) as $dailyUrl) {
            foreach ($this->readUrlSet($dailyUrl) as $url) {
                if (!$this->isFarsArticleUrl($url)) {
                    continue;
                }
                $articleUrls[$url] = true;
                if (count($articleUrls) >= $maxItems) {
                    break 2;
                }
            }
        }

        $results = [];
        foreach (array_keys($articleUrls) as $url) {
            try {
                $meta = $this->articleMetadata($url);
                if (!$meta || $meta['title'] === '') {
                    continue;
                }

                $results[] = new SourceItemData(
                    externalId: sha1($url),
                    title: $meta['title'],
                    url: $url,
                    content: $meta['description'] !== '' ? $meta['description'] : null,
                    publishedAt: $this->parseDate($meta['published_at']),
                    featuredImageUrl: $meta['image'] ?: null,
                    rawData: [
                        'source' => 'farsnews_sitemap',
                        'description' => $meta['description'],
                        'metadata_only' => true,
                    ],
                );
            } catch (Throwable $e) {
                // یک خبر خراب نباید کل اسکن منبع را متوقف کند.
                report($e);
            }
        }

        return $results;
    }

    private function yearIndexUrl(string $configuredUrl, int $year): string
    {
        if (preg_match('~/index\d{4}\.xml(?:\?.*)?$~', $configuredUrl)) {
            return preg_replace('~/index\d{4}\.xml~', '/index' . $year . '.xml', $configuredUrl) ?: $configuredUrl;
        }
        return self::DEFAULT_SITEMAP . $year . '.xml';
    }

    private function dailySitemapUrl(Carbon $date): string
    {
        return sprintf('https://farsnews.ir/sitemaps/contents/%s/%s/%s/index001.xml', $date->format('Y'), $date->format('m'), $date->format('d'));
    }

    /** @return array<int, array{url:string,lastmod:?string}> */
    private function readYearIndex(string $url): array
    {
        return Cache::remember('rased:farsnews:sitemap-index:' . sha1($url), now()->addMinutes(30), function () use ($url) {
            $xml = $this->fetchXml($url);
            $nodes = $xml->xpath('//*[local-name()="sitemap"]');
            $entries = [];
            foreach ($nodes ?: [] as $node) {
                $loc = trim((string) ($node->xpath('./*[local-name()="loc"]')[0] ?? ''));
                $lastmod = trim((string) ($node->xpath('./*[local-name()="lastmod"]')[0] ?? ''));
                if ($loc !== '') {
                    $entries[] = ['url' => $loc, 'lastmod' => $lastmod !== '' ? $lastmod : null];
                }
            }
            return $entries;
        });
    }

    /** @return array<int, string> */
    private function readUrlSet(string $url): array
    {
        return Cache::remember('rased:farsnews:urlset:' . sha1($url), now()->addMinutes(20), function () use ($url) {
            $xml = $this->fetchXml($url);
            $nodes = $xml->xpath('//*[local-name()="url"]/*[local-name()="loc"]');
            $urls = [];
            foreach ($nodes ?: [] as $node) {
                $loc = trim((string) $node);
                if ($loc !== '') {
                    $urls[] = $loc;
                }
            }
            return array_values(array_unique($urls));
        });
    }

    private function fetchXml(string $url): \SimpleXMLElement
    {
        $response = Http::connectTimeout(8)
            ->timeout(20)
            ->withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.6',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('دریافت نقشه سایت فارس ناموفق بود؛ HTTP ' . $response->status() . ' (' . $url . ')');
        }

        $body = trim($response->body());
        if ($body === '' || strlen($body) > 15 * 1024 * 1024) {
            throw new RuntimeException('پاسخ نقشه سایت فارس خالی یا بیش از حد بزرگ است: ' . $url);
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$xml) {
            throw new RuntimeException('ساختار XML نقشه سایت فارس قابل خواندن نیست: ' . $url);
        }
        return $xml;
    }

    private function isFarsArticleUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower($parts['host'] ?? ''), ['farsnews.ir', 'www.farsnews.ir'], true)) {
            return false;
        }
        $path = trim((string) ($parts['path'] ?? ''), '/');
        return $path !== '' && !str_starts_with($path, 'sitemaps/') && !str_starts_with($path, 'search/');
    }

    /** @return array{title:string,description:string,published_at:?string,image:?string}|null */
    private function articleMetadata(string $url): ?array
    {
        $cacheKey = 'rased:farsnews:metadata:' . sha1($url);
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($url) {
            $response = Http::connectTimeout(8)
                ->timeout(20)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.6',
                ])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            if ($html === '' || strlen($html) > 5 * 1024 * 1024) {
                return null;
            }

            $crawler = new Crawler($html, $url);
            $title = $this->metaValue($crawler, [
                'meta[property="og:title"]',
                'meta[name="twitter:title"]',
                'title',
            ]);
            $description = $this->metaValue($crawler, [
                'meta[name="description"]',
                'meta[property="og:description"]',
                'meta[name="twitter:description"]',
            ]);
            $publishedAt = $this->metaValue($crawler, [
                'meta[property="article:published_time"]',
                'meta[name="datePublished"]',
                'meta[property="article:modified_time"]',
            ]);
            $image = $this->metaValue($crawler, [
                'meta[property="og:image"]',
                'meta[name="twitter:image"]',
            ]);

            // عنوان فارس گاهی با پیشوند «fars |» منتشر می‌شود؛ پیشوند برند را حذف می‌کنیم.
            $title = preg_replace('/^\s*fars\s*\|\s*/iu', '', $title) ?? $title;
            return [
                'title' => $this->cleanText($title),
                'description' => $this->cleanText($description),
                'published_at' => $publishedAt !== '' ? $publishedAt : null,
                'image' => $image !== '' ? $this->absoluteUrl($image, $url) : null,
            ];
        });
    }

    private function metaValue(Crawler $crawler, array $selectors): string
    {
        foreach ($selectors as $selector) {
            try {
                $nodes = $crawler->filter($selector);
                if (!$nodes->count()) {
                    continue;
                }
                $node = $nodes->first();
                $value = $selector === 'title' ? $node->text('', true) : (string) $node->attr('content');
                $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($value !== '') {
                    return $value;
                }
            } catch (Throwable) {
                // ادامه با selector جایگزین
            }
        }
        return '';
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00A0}\s]+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function absoluteUrl(string $url, string $base): string
    {
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'farsnews.ir');
        if (str_starts_with($url, '/')) {
            return $origin . $url;
        }
        return $origin . '/' . $url;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->setTimezone('Asia/Tehran');
        } catch (Throwable) {
            return null;
        }
    }
}
