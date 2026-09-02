<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class FarsNewsReader
{
    /**
     * خواندن اخبار فارس نیوز
     */
    public function read(Source $source): array
    {
        $url = $source->settings['list_url'] ?? $source->url;

        if (!$url) {
            throw new \RuntimeException(
                'آدرس منبع فارس نیوز مشخص نشده است.'
            );
        }

        $maxItems = (int) (
            $source->settings['max_items']
            ?? 20
        );

        $maxPages = (int) (
            $source->settings['max_pages']
            ?? 1
        );

        $fetchArticleContent = (bool) (
            $source->settings['fetch_article_content']
            ?? true
        );

        $results = [];

        $visitedPages = [];

        $currentUrl = $url;

        for ($page = 1; $page <= $maxPages; $page++) {

            if (!$currentUrl) {
                break;
            }

            if (in_array($currentUrl, $visitedPages, true)) {
                break;
            }

            $visitedPages[] = $currentUrl;

            try {
                $html = $this->fetchPage($currentUrl);
            } catch (Throwable $e) {

                report($e);

                break;
            }

            $crawler = new Crawler(
                $html,
                $currentUrl
            );

            $items = $this->extractItems(
                $crawler,
                $maxItems - count($results),
                $fetchArticleContent
            );

            foreach ($items as $item) {
                $results[] = $item;

                if (count($results) >= $maxItems) {
                    break 2;
                }
            }

            /*
             * اگر صفحه بعدی وجود داشته باشد،
             * آن را پیدا می‌کنیم.
             */
            $nextUrl = $this->extractNextPageUrl(
                $crawler,
                $currentUrl
            );

            if (!$nextUrl) {
                break;
            }

            $currentUrl = $nextUrl;
        }

        return $results;
    }

    /**
     * دریافت HTML صفحه
     */
    protected function fetchPage(string $url): string
    {
        $response = Http::withoutVerifying()
            ->withHeaders([
                'User-Agent' =>
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                    . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                    . 'Chrome/139.0.0.0 Safari/537.36',

                'Accept' =>
                    'text/html,application/xhtml+xml,'
                    . 'application/xml;q=0.9,image/avif,image/webp,'
                    . '*/*;q=0.8',

                'Accept-Language' =>
                    'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',

                'Referer' =>
                    'https://farsnews.ir/',

                'Cache-Control' =>
                    'no-cache',

                'Pragma' =>
                    'no-cache',
            ])
            ->connectTimeout(15)
            ->timeout(30)
            ->retry(2, 1000)
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException(
                sprintf(
                    'FarsNews HTTP Error: %s - %s',
                    $response->status(),
                    $url
                )
            );
        }

        return $response->body();
    }
    /**
     * استخراج اخبار از صفحه جستجوی فارس
     */
    protected function extractItems(
        Crawler $crawler,
        int $remaining,
        bool $fetchArticleContent
    ): array {

        $items = [];

        if ($remaining <= 0) {
            return $items;
        }

        /*
         * ساختار فعلی فارس نیوز:
         *
         * <blockquote class="n-4df7w3" cite="...">
         *
         * داخل آن:
         *
         * h3.n-skieo1       => عنوان
         * .n-u1u0l1         => خلاصه
         * a[data-userid]    => نویسنده
         * ...
         */
        $nodes = $crawler->filter(
            'blockquote.n-4df7w3'
        );

        $nodes->each(function (
            Crawler $node
        ) use (
            &$items,
            $remaining,
            $fetchArticleContent
        ) {

            if (count($items) >= $remaining) {
                return;
            }

            try {

                /*
                 * URL خبر
                 */
                $url = trim(
                    (string) $node->attr('cite')
                );

                /*
                 * اگر cite وجود نداشت، از لینک داخل کارت
                 * استفاده می‌کنیم.
                 */
                if (!$url) {

                    $link = $node->filter(
                        'a[href]'
                    );

                    if ($link->count()) {
                        $url = trim(
                            (string) $link->first()->attr('href')
                        );
                    }
                }

                if (!$url) {
                    return;
                }

                $url = $this->absoluteUrl($url);

                /*
                 * عنوان
                 */
                $title = $this->text(
                    $node,
                    'h3.n-skieo1'
                );

                if (!$title) {

                    $title = $this->text(
                        $node,
                        'h3'
                    );
                }

                if (!$title) {
                    return;
                }

                /*
                 * خلاصه
                 */
                $summary = $this->text(
                    $node,
                    '.n-u1u0l1'
                );

                /*
                 * نویسنده
                 */
                $author = null;

                $authorNode = $node->filter(
                    'a[data-userid]'
                );

                if ($authorNode->count()) {

                    $author = trim(
                        $authorNode->first()->text()
                    );
                }

                /*
                 * متن زمان انتشار
                 *
                 * مثال:
                 * 10 ساعت پیش
                 * 2 روز پیش
                 */
                $publishedText = null;

                $dateNode = $node->filter(
                    'div.flex.justify-between.items-center.w-full.mt-2px span'
                );

                if ($dateNode->count()) {

                    $publishedText = trim(
                        $dateNode->last()->text()
                    );
                }

                /*
                 * شناسه خبر
                 *
                 * نمونه URL:
                 *
                 * https://farsnews.ir/MaryamKarami/1788294412602594881
                 */
                $externalId = $this->extractExternalId(
                    $url
                );

                /*
                 * محتوای کامل خبر
                 */
                $content = null;

                if ($fetchArticleContent) {

                    try {

                        $content = $this->fetchArticleContent(
                            $url
                        );

                    } catch (Throwable $e) {

                        /*
                         * اگر دریافت متن کامل شکست خورد،
                         * خود خبر را از دست نمی‌دهیم.
                         */
                        report($e);
                    }
                }

                /*
                 * اگر محتوای کامل پیدا نشد،
                 * حداقل خلاصه را به عنوان content قرار می‌دهیم.
                 */
                if (!$content && $summary) {
                    $content = $summary;
                }

                $items[] = new SourceItemData(
                    externalId: $externalId,
                    title: $title,
                    url: $url,
                    content: $content,
                    publishedAt: $this->parsePublishedAt(
                        $publishedText
                    ),
                    rawData: [
                        'author' => $author,
                        'summary' => $summary,
                        'published_text' => $publishedText,
                        'source' => 'farsnews',
                    ],
                );

            } catch (Throwable $e) {

                report($e);
            }
        });

        return $items;
    }

    /**
     * دریافت متن کامل خبر
     */
    protected function fetchArticleContent(
        string $url
    ): ?string {

        $html = $this->fetchPage($url);

        $crawler = new Crawler(
            $html,
            $url
        );

        /*
         * چند selector مختلف برای مقاومت در برابر
         * تغییرات جزئی ساختار سایت.
         */
        $selectors = [
            '[data-testid="article-content"]',
            '.article-content',
            '.n-article-content',
            'article',
            'main',
        ];

        foreach ($selectors as $selector) {

            $nodes = $crawler->filter($selector);

            if (!$nodes->count()) {
                continue;
            }

            $text = trim(
                $nodes->first()->text(
                    '',
                    true
                )
            );

            $text = $this->cleanText($text);

            if (
                $text !== ''
                && mb_strlen($text) >= 80
            ) {
                return $text;
            }
        }

        /*
         * اگر selectorهای بالا جواب ندادند،
         * از پاراگراف‌های صفحه استفاده می‌کنیم.
         */
        $paragraphs = $crawler->filter(
            'p'
        );

        $texts = [];

        $paragraphs->each(
            function (Crawler $p) use (&$texts) {

                $text = $this->cleanText(
                    $p->text('', true)
                );

                if (
                    $text !== ''
                    && mb_strlen($text) >= 20
                ) {
                    $texts[] = $text;
                }
            }
        );

        if ($texts) {

            $content = implode(
                "\n\n",
                array_unique($texts)
            );

            if (mb_strlen($content) >= 80) {
                return $content;
            }
        }

        return null;
    }

    /**
     * پیدا کردن صفحه بعد
     */
    protected function extractNextPageUrl(
        Crawler $crawler,
        string $currentUrl
    ): ?string {

        $next = $crawler->filter(
            'link[rel="next"]'
        );

        if (!$next->count()) {
            return null;
        }

        $url = trim(
            (string) $next->first()->attr('href')
        );

        if (!$url) {
            return null;
        }

        return $this->absoluteUrl(
            $url,
            $currentUrl
        );
    }

    /**
     * تبدیل URL نسبی به مطلق
     */
    protected function absoluteUrl(
        string $url,
        ?string $baseUrl = null
    ): string {

        if (
            Str::startsWith(
                $url,
                'http://'
            )
            ||
            Str::startsWith(
                $url,
                'https://'
            )
        ) {
            return $url;
        }

        $baseUrl ??= 'https://farsnews.ir';

        if (Str::startsWith($url, '//')) {

            return 'https:' . $url;
        }

        if (Str::startsWith($url, '/')) {

            $parsed = parse_url($baseUrl);

            $scheme = $parsed['scheme']
                ?? 'https';

            $host = $parsed['host']
                ?? 'farsnews.ir';

            return $scheme . '://' . $host . $url;
        }

        return rtrim(
                $baseUrl,
                '/'
            ) . '/' . ltrim(
                $url,
                '/'
            );
    }

    /**
     * استخراج شناسه خارجی خبر
     */
    protected function extractExternalId(
        string $url
    ): string {

        $path = parse_url(
            $url,
            PHP_URL_PATH
        );

        if ($path) {

            $segments = array_values(
                array_filter(
                    explode('/', trim($path, '/'))
                )
            );

            if ($segments) {

                $last = end($segments);

                if (
                    $last
                    && preg_match(
                        '/^\d+$/',
                        $last
                    )
                ) {
                    return $last;
                }
            }
        }

        return sha1($url);
    }

    /**
     * تبدیل متن
     */
    protected function text(
        Crawler $node,
        string $selector
    ): ?string {

        $element = $node->filter($selector);

        if (!$element->count()) {
            return null;
        }

        $text = trim(
            $element->first()->text(
                '',
                true
            )
        );

        return $this->cleanText($text);
    }

    /**
     * تمیز کردن متن
     */
    protected function cleanText(
        ?string $text
    ): ?string {

        if ($text === null) {
            return null;
        }

        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = preg_replace(
            '/[ \t]+/u',
            ' ',
            $text
        );

        $text = preg_replace(
            "/\r\n|\r/u",
            "\n",
            $text
        );

        $text = preg_replace(
            "/\n{3,}/u",
            "\n\n",
            $text
        );

        $text = trim($text);

        return $text !== ''
            ? $text
            : null;
    }

    /**
     * تبدیل زمان نسبی به Carbon
     *
     * فعلاً فقط زمانی که متن زمان قابل تشخیص باشد.
     */
    protected function parsePublishedAt(
        ?string $text
    ): ?Carbon {

        if (!$text) {
            return null;
        }

        $text = trim($text);

        /*
         * فارس نیوز معمولاً زمان نسبی نمایش می‌دهد.
         *
         * برای جلوگیری از ثبت زمان اشتباه،
         * اگر نتوانیم دقیق تبدیل کنیم null برمی‌گردانیم.
         */

        $now = now();

        /*
         * اعداد فارسی را به انگلیسی تبدیل می‌کنیم.
         */
        $normalized = strtr(
            $text,
            [
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',
                '٠' => '0',
                '١' => '1',
                '٢' => '2',
                '٣' => '3',
                '٤' => '4',
                '٥' => '5',
                '٦' => '6',
                '٧' => '7',
                '٨' => '8',
                '٩' => '9',
            ]
        );

        /*
         * همین الان
         */
        if (
            str_contains(
                $normalized,
                'لحظه'
            )
            ||
            str_contains(
                $normalized,
                'همین الان'
            )
        ) {
            return $now;
        }

        /*
         * X دقیقه پیش
         */
        if (
            preg_match(
                '/(\d+)\s*(دقیقه|دقیقه‌|min)/u',
                $normalized,
                $matches
            )
        ) {

            return $now->copy()->subMinutes(
                (int) $matches[1]
            );
        }

        /*
         * X ساعت پیش
         */
        if (
            preg_match(
                '/(\d+)\s*(ساعت|hour)/u',
                $normalized,
                $matches
            )
        ) {

            return $now->copy()->subHours(
                (int) $matches[1]
            );
        }

        /*
         * X روز پیش
         */
        if (
            preg_match(
                '/(\d+)\s*(روز|day)/u',
                $normalized,
                $matches
            )
        ) {

            return $now->copy()->subDays(
                (int) $matches[1]
            );
        }

        return null;
    }

    /**
     * User-Agent
     */
    protected function userAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) '
            . 'Chrome/139.0.0.0 Safari/537.36';
    }
}
