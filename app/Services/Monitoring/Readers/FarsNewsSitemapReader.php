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
 * خواندن اخبار فارس از نقشه‌های سایت روزانه.
 * فقط عنوان و توضیحات متادیتای صفحه ذخیره می‌شود؛ متن کامل خبر استخراج نمی‌شود.
 */
class FarsNewsSitemapReader implements SourceReaderInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    private const SITEMAP_BASE = 'https://farsnews.ir/sitemaps/contents';

    public function read(Source $source): array
    {
        $settings = is_array($source->settings) ? $source->settings : [];
        $maxItems = min(300, max(1, (int) ($settings['max_items'] ?? 100)));

        // نام کاربری‌ها با خط جدید یا ویرگول جدا می‌شوند؛ خالی بودن یعنی همه نویسندگان.
        $authorsRaw = $settings['farsnews_authors'] ?? '';
        if (is_array($authorsRaw)) {
            $authors = $authorsRaw;
        } else {
            $authors = preg_split('/[\r\n,،;]+/u', (string) $authorsRaw) ?: [];
        }
        $authors = array_values(array_unique(array_filter(array_map(
            static function ($author): string {
                $author = trim((string) $author);
                $author = preg_replace('~^https?://(?:www\.)?farsnews\.ir/~i', '', $author) ?? $author;
                return trim($author, " \t\n\r\0\x0B/@");
            },
            $authors
        ), static fn (string $author): bool => $author !== '')));

        // فقط نقشه XML امروز دریافت می‌شود و ترتیب اصلی آن (جدید به قدیم) حفظ می‌شود.
        $today = now('Asia/Tehran')->startOfDay();
        $todayDate = $today->toDateString();
        $todaySitemap = $this->dailySitemapUrl($today);

        $orderedEntries = [];
        try {
            foreach ($this->readUrlSet($todaySitemap) as $entry) {
                $url = trim($entry['url']);
                if (!$this->isFarsArticleUrl($url) || isset($orderedEntries[$url])) {
                    continue;
                }

                $orderedEntries[$url] = [
                    'url' => $url,
                    'lastmod' => $entry['lastmod'],
                ];
            }
        } catch (Throwable $e) {
            throw new RuntimeException(
                'دریافت نقشه سایت امروز فارس ناموفق بود. ' . $e->getMessage(),
                previous: $e,
            );
        }

        if (!$orderedEntries) {
            throw new RuntimeException(
                'در نقشه سایت امروز فارس آدرس خبری پیدا نشد. آدرس نقشه روزانه باید از الگوی YYYY/MM/DD/index001.xml پیروی کند.'
            );
        }

        // وضعیت پیمایش جدا از last_item_url نگهداری می‌شود؛ چون max_items ممکن است
        // باعث شود پردازش خبرهای جدید در چند نوبت تکمیل شود.
        $scanDate = (string) ($settings['farsnews_sitemap_scan_date'] ?? '');
        $cursorUrl = trim((string) ($settings['farsnews_sitemap_cursor_url'] ?? ''));
        $seenUrls = $settings['farsnews_sitemap_seen_urls'] ?? [];
        if (!is_array($seenUrls)) {
            $seenUrls = [];
        }
        $seenSet = array_fill_keys(array_map('strval', $seenUrls), true);

        if ($scanDate !== $todayDate) {
            // هر روز فقط نقشه همان روز ملاک است؛ وضعیت پیمایش روز قبل پاک می‌شود.
            $cursorUrl = '';
            $seenSet = [];
        } elseif ($cursorUrl === '' && ($settings['farsnews_sitemap_cursor_mode'] ?? '') === 'newest_first_v1') {
            // مهاجرت از نشانگر نسخه قبلی، فقط وقتی لینک در نقشه امروز وجود داشته باشد.
            $legacyUrl = trim((string) $source->last_item_url);
            if ($legacyUrl !== '' && isset($orderedEntries[$legacyUrl])) {
                $cursorUrl = $legacyUrl;
            }
        }

        $cursorExistsInToday = $cursorUrl !== '' && isset($orderedEntries[$cursorUrl]);
        if ($cursorUrl !== '' && !$cursorExistsInToday) {
            // نشانگر متعلق به روز قبل/نقشه قدیمی است؛ امروز از ابتدای نقشه شروع می‌کنیم.
            $cursorUrl = '';
            $seenSet = [];
        }

        $results = [];
        $metadataFailures = 0;
        $metadataErrors = [];
        $attempted = 0;
        $limitReached = false;
        $reachedCursor = false;

        foreach ($orderedEntries as $url => $entry) {
            // به نشانگرِ آخرین پیمایش کامل رسیده‌ایم؛ آدرس‌های قدیمی‌تر متعلق به گذشته‌اند.
            if ($cursorUrl !== '' && $url === $cursorUrl) {
                $reachedCursor = true;
                break;
            }

            // فیلتر نویسنده قبل از هر درخواست به صفحه خبر اعمال می‌شود.
            if (!$this->matchesAuthor($url, $authors)) {
                continue;
            }

            // این لینک در یک نوبت قبلی از همین پیمایش باز شده است؛ دوباره درخواست نمی‌زنیم.
            if (isset($seenSet[$url])) {
                continue;
            }

            if ($attempted >= $maxItems) {
                $limitReached = true;
                break;
            }
            $attempted++;

            try {
                $meta = $this->articleMetadata($url);
                if (!$meta || $meta['title'] === '') {
                    $metadataFailures++;
                    $metadataErrors[] = 'عنوان از HTML قابل استخراج نبود: ' . $url;
                    continue;
                }

                // فقط پس از استخراج موفق متادیتا، URL را بازشده علامت می‌زنیم.
                $seenSet[$url] = true;
                $publishedAt = $this->parseDate($meta['published_at'] ?: $entry['lastmod']);
                $results[] = new SourceItemData(
                    externalId: sha1($url),
                    title: $meta['title'],
                    url: $url,
                    content: $meta['description'] !== '' ? $meta['description'] : null,
                    publishedAt: $publishedAt,
                    featuredImageUrl: $meta['image'] ?: null,
                    rawData: [
                        'source' => 'farsnews_sitemap',
                        'author' => $this->authorFromUrl($url),
                        'description' => $meta['description'],
                        'sitemap_lastmod' => $entry['lastmod'],
                        'metadata_only' => true,
                    ],
                );
            } catch (Throwable $e) {
                $metadataFailures++;
                $metadataErrors[] = $e->getMessage();
                report($e);
            }
        }

        // اگر به نشانگر رسیدیم یا کل نقشه را پیمودیم، پیمایش کامل شده است.
        // نشانگر به ابتدای XML فعلی منتقل می‌شود تا اجرای بعدی فقط لینک‌های تازه‌تر را ببیند.
        $scanComplete = !$limitReached && $metadataFailures === 0
            && ($reachedCursor || ($cursorUrl === '' && !$cursorExistsInToday));
        if ($scanComplete) {
            $settings['farsnews_sitemap_cursor_url'] = (string) array_key_first($orderedEntries);
            $settings['farsnews_sitemap_seen_urls'] = [];
        } else {
            $settings['farsnews_sitemap_cursor_url'] = $cursorUrl;
            $settings['farsnews_sitemap_seen_urls'] = array_values(array_keys($seenSet));
        }
        $settings['farsnews_sitemap_scan_date'] = $todayDate;
        $settings['farsnews_sitemap_cursor_mode'] = 'newest_first_v1';
        $source->settings = $settings;

        if (!$results && $metadataFailures > 0) {
            $detail = $metadataErrors ? ' اولین خطا: ' . $metadataErrors[0] : '';
            throw new RuntimeException(
                'از نقشه سایت امروز فارس ' . $attempted .
                ' آدرسِ منطبق با نویسندگان انتخاب‌شده بررسی شد، اما عنوان هیچ‌کدام قابل استخراج نبود.' .
                ' تعداد پاسخ‌های ناموفق/بدون عنوان: ' . $metadataFailures . '.' . $detail .
                ' اگر خطا HTTP 403 یا 429 است، احتمالاً دسترسی هاست به صفحه خبر محدود شده است.'
            );
        }

        // نبودن خبر از نویسندگان انتخاب‌شده، خطا نیست.
        return $results;
    }

    /**
     * تطبیق نام نویسنده با اولین بخش مسیر؛ مثلاً /NargesJafari/...
     *
     * @param array<int,string> $authors
     */
    private function matchesAuthor(string $url, array $authors): bool
    {
        if ($authors === []) {
            return true;
        }

        $author = $this->authorFromUrl($url);
        foreach ($authors as $wanted) {
            if (mb_strtolower($author, 'UTF-8') === mb_strtolower($wanted, 'UTF-8')) {
                return true;
            }
        }

        return false;
    }

    private function authorFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        return trim((string) ($segments[0] ?? ''));
    }

    private function dailySitemapUrl(Carbon $date): string
    {
        return sprintf('%s/%s/%s/%s/index001.xml', self::SITEMAP_BASE, $date->format('Y'), $date->format('m'), $date->format('d'));
    }

    /** @return array<int, array{url:string,lastmod:?string}> */
    private function readUrlSet(string $url): array
    {
        // نسخه v2 مانع استفاده از کش قدیمیِ قالب قبلی می‌شود.
        $key = 'rased:farsnews:urlset:v2:' . sha1($url);
        $entries = Cache::remember($key, now()->addMinutes(10), function () use ($url) {
            $xml = $this->fetchXml($url);
            $nodes = $xml->xpath('//*[local-name()="url"]');
            $entries = [];
            foreach ($nodes ?: [] as $node) {
                $locNodes = $node->xpath('./*[local-name()="loc"]');
                $lastmodNodes = $node->xpath('./*[local-name()="lastmod"]');
                $loc = trim((string) ($locNodes[0] ?? ''));
                $lastmod = trim((string) ($lastmodNodes[0] ?? ''));
                if ($loc !== '') {
                    $entries[] = ['url' => $loc, 'lastmod' => $lastmod !== '' ? $lastmod : null];
                }
            }
            return $entries;
        });

        if (!$entries) {
            Cache::forget($key);
        }
        return $entries;
    }

    private function fetchXml(string $url): \SimpleXMLElement
    {
        $response = Http::connectTimeout(8)
            ->timeout(25)
            ->withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
                'Referer' => 'https://farsnews.ir/',
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
        return $path !== ''
            && !str_starts_with($path, 'sitemaps/')
            && !str_starts_with($path, 'search/');
    }

    /** @return array{title:string,description:string,published_at:?string,image:?string}|null */
    private function articleMetadata(string $url): ?array
    {
        $cacheKey = 'rased:farsnews:metadata:v2:' . sha1($url);
        $meta = Cache::remember($cacheKey, now()->addHours(6), function () use ($url) {
            $response = Http::connectTimeout(8)
                ->timeout(25)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Referer' => 'https://farsnews.ir/',
                    'Upgrade-Insecure-Requests' => '1',
                ])
                ->get($url);

            if (!$response->successful()) {
                throw new RuntimeException('دریافت صفحه خبر ناموفق بود؛ HTTP ' . $response->status() . ' (' . $url . ')');
            }

            $html = $response->body();
            if ($html === '' || strlen($html) > 5 * 1024 * 1024) {
                throw new RuntimeException('پاسخ HTML خبر خالی یا بیش از حد بزرگ است (' . $url . ')');
            }

            // استخراج متادیتا با PHP خالص؛ نیازی به Symfony DomCrawler نیست.
            $metadata = $this->extractHtmlMetadata($html);
            $title = $metadata['title'];
            $description = $metadata['description'];
            $publishedAt = $metadata['published_at'];
            $image = $metadata['image'];

            $title = preg_replace('/^\s*fars\s*\|\s*/iu', '', $title) ?? $title;
            $cleanTitle = $this->cleanText($title);
            if ($cleanTitle === '') {
                // برای خطای قابل تشخیص، نوع پاسخ را گزارش می‌کنیم نه محتوای صفحه را.
                $contentType = (string) $response->header('Content-Type', 'نامشخص');
                throw new RuntimeException('پاسخ صفحه خبر HTTP 200 داشت، اما عنوان در HTML پیدا نشد؛ Content-Type=' . $contentType . ' (' . $url . ')');
            }

            return [
                'title' => $cleanTitle,
                'description' => $this->cleanText($description),
                'published_at' => $publishedAt !== '' ? $publishedAt : null,
                'image' => $image !== '' ? $this->absoluteUrl($image, $url) : null,
            ];
        });

        if (!$meta) {
            Cache::forget($cacheKey);
        }
        return $meta;
    }

    /**
     * استخراج عنوان، توضیحات، تاریخ و تصویر بدون وابستگی خارجی.
     * فقط متادیتای HTML خوانده می‌شود و متن کامل خبر استخراج نمی‌شود.
     *
     * @return array{title:string,description:string,published_at:string,image:string}
     */
    private function extractHtmlMetadata(string $html): array
    {
        $meta = [];

        if (preg_match_all('~<meta\b[^>]*>~is', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                $attributes = $this->parseHtmlAttributes($tag);
                $key = strtolower(trim(
                    $attributes['property']
                    ?? $attributes['name']
                    ?? ''
                ));
                $value = trim((string) ($attributes['content'] ?? ''));

                if ($key !== '' && $value !== '' && !isset($meta[$key])) {
                    $meta[$key] = $value;
                }
            }
        }

        $title = $meta['og:title']
            ?? $meta['twitter:title']
            ?? $meta['title']
            ?? '';

        if ($title === '' && preg_match('~<title\b[^>]*>(.*?)</title\s*>~is', $html, $match)) {
            $title = $match[1];
        }

        if ($title === '' && preg_match('~<h1\b[^>]*>(.*?)</h1\s*>~is', $html, $match)) {
            $title = $match[1];
        }

        $description = $meta['description']
            ?? $meta['og:description']
            ?? $meta['twitter:description']
            ?? '';

        $publishedAt = $meta['article:published_time']
            ?? $meta['datepublished']
            ?? $meta['article:modified_time']
            ?? '';

        if ($publishedAt === '' && preg_match('~<time\b[^>]*>~is', $html, $match)) {
            $timeAttributes = $this->parseHtmlAttributes($match[0]);
            $publishedAt = (string) ($timeAttributes['datetime'] ?? '');
        }

        $image = $meta['og:image']
            ?? $meta['twitter:image']
            ?? '';

        return [
            'title' => $this->cleanText($title),
            'description' => $this->cleanText($description),
            'published_at' => trim($publishedAt),
            'image' => trim($image),
        ];
    }

    /** @return array<string,string> */
    private function parseHtmlAttributes(string $tag): array
    {
        $attributes = [];
        $pattern = '~([a-zA-Z_:][a-zA-Z0-9_:.-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~s';

        if (preg_match_all($pattern, $tag, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                $value = $match[2] !== '' ? $match[2]
                    : (($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? ''));

                $attributes[$name] = html_entity_decode(
                    $value,
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );
            }
        }

        return $attributes;
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
