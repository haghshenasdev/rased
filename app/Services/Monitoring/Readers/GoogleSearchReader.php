<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleSearchReader implements SourceReaderInterface
{
    public function read(Source $source): array
    {
        $query = trim((string) ($source->settings['query'] ?? ''));

        if ($query === '') {
            throw new RuntimeException(
                'کلمه کلیدی جست‌وجوی Google برای این منبع مشخص نشده است.'
            );
        }

        $count = min(10, max(1, (int) ($source->settings['count'] ?? 10)));
        $key = trim((string) config('services.google_search.key'));
        $cx = trim((string) config('services.google_search.cx'));

        // اگر API تنظیم شده، اولویت با API رسمی است.
        if ($key !== '' && $cx !== '') {
            $response = Http::connectTimeout(10)
                ->timeout(20)
                ->withHeaders([
                    'User-Agent' =>
                            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                            'AppleWebKit/537.36 ' .
                            '(KHTML, like Gecko) ' .
                            'Chrome/139.0 Safari/537.36',
                    'Accept' => 'application/json',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get('https://www.googleapis.com/customsearch/v1', [
                    'key' => $key,
                    'cx' => $cx,
                    'q' => $query,
                    'num' => $count,
                    'hl' => 'fa',
                    'safe' => 'off',
                ]);

            // خطای سهمیه/محدودیت API را با scraping دور نمی‌زنیم.
            if (!$response->successful()) {
                throw new RuntimeException(
                    'Google Custom Search: HTTP ' . $response->status()
                    . ' - ' . mb_substr($response->body(), 0, 500)
                );
            }

            $items = $response->json('items', []);
            if (is_array($items) && count($items) > 0) {
                return $this->mapApiItems($items, $query);
            }
        }

        // HTML فقط به‌عنوان مسیر جایگزین محدود و با رعایت محدودیت دسترسی استفاده می‌شود.
        return $this->readHtmlResults($query, $count);
    }

    protected function mapApiItems(array $items, string $query): array
    {
        $result = [];
        $seen = [];

        foreach ($items as $item) {
            $link = trim((string) ($item['link'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));

            if ($link === '' || $title === '') {
                continue;
            }

            $normalizedLink = $this->normalizeResultUrl($link);
            if ($normalizedLink === '' || isset($seen[$normalizedLink])) {
                continue;
            }
            $seen[$normalizedLink] = true;

            $image = $item['pagemap']['cse_image'][0]['src']
                ?? $item['pagemap']['metatags'][0]['og:image']
                ?? null;

            $published = $item['pagemap']['metatags'][0]['article:published_time'] ?? null;
            $publishedAt = $this->parseDate($published);

            $result[] = new SourceItemData(
                externalId: sha1($normalizedLink),
                title: $title,
                url: $normalizedLink,
                content: trim((string) ($item['snippet'] ?? '')),
                publishedAt: $publishedAt,
                featuredImageUrl: is_string($image) ? $image : null,
                rawData: [
                    'google_query' => $query,
                    'displayLink' => $item['displayLink'] ?? null,
                    'google_method' => 'custom_search_api',
                ],
            );
        }

        return $result;
    }

    /**
     * دریافت یک صفحه HTML عمومی گوگل؛ بدون تلاش برای دور زدن CAPTCHA یا محدودیت‌ها.
     */
    protected function readHtmlResults(string $query, int $count): array
    {
        // محدودکننده مشترک برای کاهش درخواست‌های هم‌زمان در workerها.
        if (!Cache::add('rased_google_html_search_cooldown', true, now()->addSeconds(5))) {
            Log::info('Google HTML fallback skipped due to request cooldown.');
            return [];
        }

        try {
            $response = Http::connectTimeout(8)
                ->timeout(15)
                ->withHeaders([
                    'User-Agent' =>
                            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                            'AppleWebKit/537.36 ' .
                            '(KHTML, like Gecko) ' .
                            'Chrome/139.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get('https://www.google.com/search', [
                    'q' => $query,
                    'num' => $count,
                    'hl' => 'fa',
                ]);

            if (in_array($response->status(), [403, 429, 503], true)) {
                Log::warning('Google HTML fallback stopped after access limit response.', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            if (!$response->successful()) {
                Log::warning('Google HTML fallback failed.', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            $html = $response->body();

            // صفحه CAPTCHA/consent را پردازش نمی‌کنیم و برای دور زدنش تلاش مجدد نداریم.
            if (preg_match('/(unusual traffic|our systems have detected|captcha|consent\.google\.com)/i', $html)) {
                Log::warning('Google HTML fallback received a challenge or consent page; stopping.');
                return [];
            }

            return $this->parseGoogleHtml($html, $query, $count);
        } catch (\Throwable $e) {
            Log::warning('Google HTML fallback request failed.', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function parseGoogleHtml(string $html, string $query, int $count): array
    {
        if ($html === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $headings = $xpath->query('//a[.//h3]');
        $result = [];
        $seen = [];

        foreach ($headings as $anchor) {
            if (count($result) >= $count) {
                break;
            }

            $href = trim((string) $anchor->getAttribute('href'));
            $titleNode = $xpath->query('.//h3', $anchor)->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';

            if ($href === '' || $title === '') {
                continue;
            }

            // لینک‌های داخلی گوگل و لینک‌های بدون مقصد معتبر کنار گذاشته می‌شوند.
            if (str_starts_with($href, '/url?')) {
                $parts = parse_url('https://www.google.com' . $href);
                parse_str($parts['query'] ?? '', $params);
                $href = (string) ($params['q'] ?? $params['url'] ?? '');
            } elseif (str_starts_with($href, '//')) {
                $href = 'https:' . $href;
            } else {
                $parts = parse_url($href);
                $host = strtolower((string) ($parts['host'] ?? ''));
                if (($host === 'www.google.com' || $host === 'google.com')
                    && ($parts['path'] ?? '') === '/url') {
                    parse_str($parts['query'] ?? '', $params);
                    $href = (string) ($params['q'] ?? $params['url'] ?? '');
                }
            }

            if (!filter_var($href, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($href, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                continue;
            }

            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            if ($host === '' || $host === 'google.com'
                || str_ends_with($host, '.google.com')
                || $host === 'googleusercontent.com'
                || str_ends_with($host, '.googleusercontent.com')) {
                continue;
            }

            $normalizedLink = $this->normalizeResultUrl($href);
            if ($normalizedLink === '' || isset($seen[$normalizedLink])) {
                continue;
            }
            $seen[$normalizedLink] = true;

            $snippet = '';
            $container = $anchor;
            for ($level = 0; $level < 5 && $container->parentNode; $level++) {
                $container = $container->parentNode;
                $candidate = trim(preg_replace('/\s+/u', ' ', $container->textContent ?? ''));
                if (mb_strlen($candidate) >= mb_strlen($title) + 30 && mb_strlen($candidate) <= 1600) {
                    $snippet = mb_substr($candidate, 0, 500);
                    break;
                }
            }

            $result[] = new SourceItemData(
                externalId: sha1($normalizedLink),
                title: $title,
                url: $normalizedLink,
                content: $snippet,
                publishedAt: null,
                featuredImageUrl: null,
                rawData: [
                    'google_query' => $query,
                    'google_method' => 'html_fallback',
                ],
            );
        }

        return $result;
    }

    protected function normalizeResultUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
            foreach ($params as $key => $value) {
                if (preg_match('/^(utm_.+|gclid|fbclid|yclid|mc_cid|mc_eid)$/i', (string) $key)) {
                    continue;
                }
                $query[$key] = $value;
            }
            ksort($query);
        }

        $normalized = $scheme . '://' . $host . $path;
        if ($query !== []) {
            $normalized .= '?' . http_build_query($query);
        }

        return $normalized;
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->setTimezone('Asia/Tehran');
        } catch (\Throwable) {
            return null;
        }
    }
}
