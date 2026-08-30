<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Playwright\Playwright;
use Throwable;

class BrowserReader implements SourceReaderInterface
{
    /**
     * حداکثر زمان انتظار برای لود صفحه
     */
    protected int $navigationTimeout = 30000;

    /**
     * مدت انتظار بعد از لود صفحه برای اجرای JS
     */
    protected int $waitAfterLoad = 2000;

    /**
     * حداکثر تعداد خبرهایی که از صفحه لیست می‌خوانیم.
     */
    protected int $maxItems = 30;

    /**
     * اجرای Reader
     */
    public function read(Source $source): array
    {
        $settings = $source->settings ?? [];

        $listUrl = $settings['list_url']
            ?? $source->url;

        if (!$listUrl) {
            throw new \RuntimeException(
                "آدرس صفحه برای منبع [{$source->name}] مشخص نشده است."
            );
        }

        $browser = null;
        $context = null;

        try {
            /*
             * ساخت Browser
             */
            $browser = Playwright::chromium([
                'headless' => true,

                'args' => [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--no-first-run',
                    '--no-zygote',
                    '--single-process',
                ],
            ]);

            /*
             * Context
             */
            $context = $browser->newContext([
                'locale' => 'fa-IR',

                'timezoneId' =>
                    'Asia/Tehran',

                'userAgent' =>
                    'Mozilla/5.0 (X11; Linux x86_64) ' .
                    'AppleWebKit/537.36 ' .
                    '(KHTML, like Gecko) ' .
                    'Chrome/139.0.0.0 Safari/537.36',

                'viewport' => [
                    'width' => 1440,
                    'height' => 900,
                ],

                'ignoreHTTPSErrors' => true,
            ]);

            /*
             * Page لیست
             */
            $page = $context->newPage();

            $this->configurePage(
                $page,
                $settings
            );

            /*
             * باز کردن صفحه
             */
            $page->goto(
                $listUrl,
                [
                    'waitUntil' =>
                        'domcontentloaded',

                    'timeout' =>
                        $this->navigationTimeout,
                ]
            );

            /*
             * صبر برای اجرای JavaScript
             */
            $this->waitForPage(
                $page,
                $settings
            );

            /*
             * اگر selector خاصی تعیین شده،
             * تا ظاهر شدن آن صبر کن.
             */
            if (!empty(
                $settings['wait_for']
            )) {
                try {
                    $page->locator(
                        $settings['wait_for']
                    )->first()->waitFor([
                        'state' => 'visible',
                        'timeout' =>
                            $settings['wait_for_timeout']
                            ?? 15000,
                    ]);
                } catch (Throwable $e) {
                    logger()->debug(
                        'BrowserReader wait_for timeout',
                        [
                            'source' =>
                                $source->name,

                            'selector' =>
                                $settings['wait_for'],

                            'error' =>
                                $e->getMessage(),
                        ]
                    );
                }
            }

            /*
             * استخراج آیتم‌های صفحه
             */
            $items = $this->extractItems(
                $page,
                $settings
            );

            /*
             * محدود کردن تعداد
             */
            $items = array_slice(
                $items,
                0,
                (int) (
                    $settings['max_items']
                    ?? $this->maxItems
                )
            );

            /*
             * اگر لینک خبرها نسبی بود،
             * تبدیل به absolute URL می‌کنیم.
             */
            foreach ($items as &$item) {
                if (!empty($item['url'])) {
                    $item['url'] =
                        $this->absoluteUrl(
                            $listUrl,
                            $item['url']
                        );
                }
            }

            unset($item);

            /*
             * حالا هر خبر را جداگانه باز می‌کنیم.
             *
             * این قسمت مهم است:
             * صفحه لیست فقط برای پیدا کردن
             * خبرهای جدید است.
             *
             * محتوای واقعی از صفحه خود خبر
             * استخراج می‌شود.
             */
            $result = [];

            foreach ($items as $index => $item) {
                try {
                    $sourceItem =
                        $this->readArticle(
                            $context,
                            $item,
                            $settings,
                            $index
                        );

                    if ($sourceItem) {
                        $result[] =
                            $sourceItem;
                    }

                } catch (Throwable $e) {
                    logger()->warning(
                        'BrowserReader article failed',
                        [
                            'source' =>
                                $source->name,

                            'url' =>
                                $item['url']
                                ?? null,

                            'title' =>
                                $item['title']
                                ?? null,

                            'error' =>
                                $e->getMessage(),
                        ]
                    );
                }
            }

            return $result;

        } finally {
            /*
             * Context و Browser حتماً بسته شوند.
             */
            try {
                if ($context) {
                    $context->close();
                }
            } catch (Throwable) {
            }

            try {
                if ($browser) {
                    $browser->close();
                }
            } catch (Throwable) {
            }
        }
    }

    /**
     * تنظیمات Page
     */
    protected function configurePage(
        mixed $page,
        array $settings
    ): void {
        /*
         * جلوگیری از دریافت منابع غیرضروری
         *
         * در صورت فعال بودن.
         */
        if (
            ($settings['block_resources'] ?? false)
        ) {
            $page->route(
                '**/*',
                function ($route) {
                    $request =
                        $route->request();

                    $resourceType =
                        $request->resourceType();

                    if (
                        in_array(
                            $resourceType,
                            [
                                'image',
                                'font',
                                'media',
                            ],
                            true
                        )
                    ) {
                        $route->abort();
                        return;
                    }

                    $route->continue();
                }
            );
        }
    }

    /**
     * انتظار برای آماده شدن صفحه
     */
    protected function waitForPage(
        mixed $page,
        array $settings
    ): void {
        /*
         * اول network idle
         */
        try {
            $page->waitForLoadState(
                'networkidle',
                [
                    'timeout' =>
                        $settings[
                            'network_idle_timeout'
                        ]
                        ?? 10000,
                ]
            );
        } catch (Throwable) {
            /*
             * بعضی سایت‌ها همیشه connection باز دارند.
             * در این حالت timeout طبیعی است.
             */
        }

        /*
         * سپس کمی فرصت برای Render شدن JS.
         */
        $wait =
            (int) (
                $settings['wait_after_load']
                ?? $this->waitAfterLoad
            );

        if ($wait > 0) {
            usleep(
                $wait * 1000
            );
        }
    }

    /**
     * استخراج خبرها از صفحه لیست
     */
    protected function extractItems(
        mixed $page,
        array $settings
    ): array {
        $itemSelector =
            $settings['item_selector']
            ?? 'article';

        $titleSelector =
            $settings['title_selector']
            ?? 'h2, h3, .title';

        $linkSelector =
            $settings['link_selector']
            ?? 'a';

        $dateSelector =
            $settings['date_selector']
            ?? null;

        $items = [];

        $locator =
            $page->locator(
                $itemSelector
            );

        $count =
            $locator->count();

        /*
         * اگر selector اصلی چیزی پیدا نکرد،
         * fallback عمومی.
         */
        if ($count === 0) {
            $locator =
                $page->locator(
                    'article, .news-item, .post, li'
                );

            $count =
                $locator->count();
        }

        for (
            $i = 0;
            $i < $count;
            $i++
        ) {
            if (
                count($items) >=
                (
                    $settings['max_items']
                    ?? $this->maxItems
                )
            ) {
                break;
            }

            try {
                $item =
                    $locator->nth($i);

                $title =
                    $this->extractText(
                        $item,
                        $titleSelector
                    );

                $url =
                    $this->extractHref(
                        $item,
                        $linkSelector
                    );

                $date = null;

                if ($dateSelector) {
                    $date =
                        $this->extractText(
                            $item,
                            $dateSelector
                        );
                }

                /*
                 * اگر عنوان یا لینک نداریم،
                 * احتمالاً این آیتم خبر نیست.
                 */
                if (
                    trim($title) === '' &&
                    trim($url) === ''
                ) {
                    continue;
                }

                /*
                 * اگر عنوان پیدا نشد،
                 * کل متن کوتاه آیتم را امتحان کن.
                 */
                if (
                    trim($title) === ''
                ) {
                    try {
                        $title =
                            trim(
                                $item->innerText()
                            );
                    } catch (Throwable) {
                        $title = '';
                    }
                }

                /*
                 * اگر لینک داخل selector نبود،
                 * اولین a را امتحان کن.
                 */
                if (
                    trim($url) === ''
                ) {
                    $url =
                        $this->extractHref(
                            $item,
                            'a'
                        );
                }

                if (
                    trim($url) === ''
                ) {
                    continue;
                }

                $items[] = [
                    'title' =>
                        $this->cleanText(
                            $title
                        ),

                    'url' =>
                        trim($url),

                    'date' =>
                        $date
                        ? $this->cleanText(
                            $date
                        )
                        : null,

                    'list_index' =>
                        $i,
                ];

            } catch (Throwable $e) {
                logger()->debug(
                    'BrowserReader list item failed',
                    [
                        'index' => $i,
                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }
        }

        return $items;
    }

    /**
     * خواندن صفحه یک خبر
     */
    protected function readArticle(
        mixed $context,
        array $item,
        array $settings,
        int $index
    ): ?SourceItemData {
        $url =
            trim(
                $item['url'] ?? ''
            );

        if ($url === '') {
            return null;
        }

        $page =
            $context->newPage();

        try {
            $this->configurePage(
                $page,
                $settings
            );

            $page->goto(
                $url,
                [
                    'waitUntil' =>
                        'domcontentloaded',

                    'timeout' =>
                        $settings[
                            'article_timeout'
                        ]
                        ?? $this->navigationTimeout,
                ]
            );

            /*
             * صبر برای JS
             */
            $this->waitForPage(
                $page,
                $settings
            );

            /*
             * اگر selector محتوای خبر مشخص شده،
             * تا ظاهر شدن آن صبر کن.
             */
            $contentSelector =
                $settings[
                    'content_selector'
                ]
                ?? 'article, main';

            try {
                $contentLocator =
                    $page->locator(
                        $contentSelector
                    )->first();

                $contentLocator->waitFor([
                    'state' =>
                        'visible',

                    'timeout' =>
                        $settings[
                            'content_timeout'
                        ]
                        ?? 10000,
                ]);
            } catch (Throwable) {
                /*
                 * fallback به body
                 */
            }

            /*
             * استخراج عنوان
             */
            $title =
                $this->extractArticleTitle(
                    $page,
                    $settings
                );

            if (
                $title === ''
            ) {
                $title =
                    $item['title']
                    ?? '';
            }

            /*
             * استخراج محتوا
             */
            $content =
                $this->extractArticleContent(
                    $page,
                    $settings
                );

            /*
             * اگر content_selector اشتباه بود،
             * body را به عنوان fallback می‌گیریم.
             */
            if (
                trim($content) === ''
            ) {
                try {
                    $content =
                        $page
                            ->locator('body')
                            ->innerText();
                } catch (Throwable) {
                    $content = '';
                }
            }

            $content =
                $this->cleanText(
                    $content
                );

            /*
             * حذف title از ابتدای content
             * در صورت نیاز لازم نیست؛
             * چون KeywordMatcher مشکلی با آن ندارد.
             */

            /*
             * شناسه پایدار خبر
             */
            $externalId =
                $this->makeExternalId(
                    $url,
                    $item
                );

            /*
             * تاریخ
             */
            $publishedAt =
                $this->extractPublishedAt(
                    $page,
                    $item,
                    $settings
                );

            /*
             * اگر متن خیلی کم بود،
             * این صفحه احتمالاً خبر واقعی نیست.
             */
            $minimumContent =
                (int) (
                    $settings[
                        'minimum_content_length'
                    ]
                    ?? 100
                );

            if (
                mb_strlen($content) <
                $minimumContent
            ) {
                logger()->debug(
                    'BrowserReader article content too short',
                    [
                        'url' => $url,
                        'length' =>
                            mb_strlen($content),
                    ]
                );

                /*
                 * با این حال اگر title داریم،
                 * خبر را کاملاً حذف نکن.
                 */
            }

            return new SourceItemData(
                externalId:
                    $externalId,

                title:
                    $title,

                url:
                    $url,

                content:
                    $content,

                publishedAt:
                    $publishedAt,

                rawData: [
                    'reader' =>
                        'browser',

                    'list_index' =>
                        $index,

                    'list_title' =>
                        $item['title']
                        ?? null,

                    'list_date' =>
                        $item['date']
                        ?? null,

                    'page_title' =>
                        $this->safePageTitle(
                            $page
                        ),

                    'content_selector' =>
                        $contentSelector,

                    'source_url' =>
                        $url,
                ],
            );

        } finally {
            try {
                $page->close();
            } catch (Throwable) {
            }
        }
    }

    /**
     * عنوان صفحه خبر
     */
    protected function extractArticleTitle(
        mixed $page,
        array $settings
    ): string {
        $selectors = [
            $settings[
                'article_title_selector'
            ] ?? null,

            'article h1',
            'main h1',
            'h1',
            '[data-testid="article-title"]',
            '.article-title',
            '.post-title',
            '.news-title',
        ];

        foreach ($selectors as $selector) {
            if (!$selector) {
                continue;
            }

            try {
                $locator =
                    $page
                        ->locator($selector)
                        ->first();

                if (
                    $locator->count() === 0
                ) {
                    continue;
                }

                $text =
                    trim(
                        $locator->innerText()
                    );

                if ($text !== '') {
                    return $this->cleanText(
                        $text
                    );
                }

            } catch (Throwable) {
                continue;
            }
        }

        /*
         * title خود document
         */
        try {
            $title =
                trim(
                    $page->title()
                );

            if ($title !== '') {
                return $this->cleanText(
                    $title
                );
            }
        } catch (Throwable) {
        }

        return '';
    }

    /**
     * محتوای صفحه خبر
     */
    protected function extractArticleContent(
        mixed $page,
        array $settings
    ): string {
        $selectors = [
            $settings[
                'content_selector'
            ] ?? null,

            'article',
            'main',
            '[data-testid="article-content"]',
            '.article-content',
            '.article-body',
            '.post-content',
            '.post-body',
            '.news-content',
            '.news-body',
            '.story-body',
            '.content-body',
        ];

        /*
         * selectorهای تکراری حذف شوند.
         */
        $selectors =
            array_values(
                array_unique(
                    array_filter(
                        $selectors
                    )
                )
            );

        $best = '';

        foreach ($selectors as $selector) {
            try {
                $locator =
                    $page
                        ->locator($selector);

                $count =
                    $locator->count();

                if ($count === 0) {
                    continue;
                }

                /*
                 * چند مورد را بررسی کن و
                 * طولانی‌ترین متن را انتخاب کن.
                 */
                $limit =
                    min($count, 5);

                for (
                    $i = 0;
                    $i < $limit;
                    $i++
                ) {
                    try {
                        $text =
                            $locator
                                ->nth($i)
                                ->innerText();

                        $text =
                            $this->cleanText(
                                $text
                            );

                        if (
                            mb_strlen($text) >
                            mb_strlen($best)
                        ) {
                            $best =
                                $text;
                        }
                    } catch (Throwable) {
                    }
                }

                /*
                 * اگر متن مناسب پیدا شد،
                 * ادامه لازم نیست.
                 */
                if (
                    mb_strlen($best) >= 500
                ) {
                    break;
                }

            } catch (Throwable) {
                continue;
            }
        }

        return trim($best);
    }

    /**
     * استخراج تاریخ انتشار
     */
    protected function extractPublishedAt(
        mixed $page,
        array $item,
        array $settings
    ): ?Carbon {
        /*
         * اول metaهای استاندارد.
         */
        $selectors = [
            'meta[property="article:published_time"]',
            'meta[property="og:published_time"]',
            'meta[name="publish_date"]',
            'meta[name="date"]',
            'meta[itemprop="datePublished"]',
            'time[datetime]',
        ];

        foreach ($selectors as $selector) {
            try {
                $locator =
                    $page
                        ->locator($selector)
                        ->first();

                if (
                    $locator->count() === 0
                ) {
                    continue;
                }

                $value = null;

                /*
                 * meta
                 */
                if (
                    str_starts_with(
                        $selector,
                        'meta'
                    )
                ) {
                    $value =
                        $locator->getAttribute(
                            'content'
                        );
                } else {
                    $value =
                        $locator->getAttribute(
                            'datetime'
                        );

                    if (!$value) {
                        $value =
                            $locator->innerText();
                    }
                }

                if (!$value) {
                    continue;
                }

                return Carbon::parse(
                    trim($value)
                );

            } catch (Throwable) {
                continue;
            }
        }

        /*
         * اگر تاریخ از صفحه پیدا نشد،
         * تاریخ صفحه لیست.
         */
        if (
            !empty($item['date'])
        ) {
            try {
                return Carbon::parse(
                    $item['date']
                );
            } catch (Throwable) {
            }
        }

        return null;
    }

    /**
     * استخراج متن از یک locator
     */
    protected function extractText(
        mixed $item,
        string $selector
    ): string {
        try {
            $locator =
                $item
                    ->locator($selector)
                    ->first();

            if (
                $locator->count() === 0
            ) {
                return '';
            }

            return trim(
                $locator->innerText()
            );

        } catch (Throwable) {
            return '';
        }
    }

    /**
     * استخراج href
     */
    protected function extractHref(
        mixed $item,
        string $selector
    ): string {
        try {
            $locator =
                $item
                    ->locator($selector)
                    ->first();

            if (
                $locator->count() === 0
            ) {
                return '';
            }

            return trim(
                (string) (
                    $locator->getAttribute(
                        'href'
                    ) ?? ''
                )
            );

        } catch (Throwable) {
            return '';
        }
    }

    /**
     * URL نسبی → Absolute
     */
    protected function absoluteUrl(
        string $baseUrl,
        string $url
    ): string {
        $url =
            trim($url);

        if ($url === '') {
            return '';
        }

        /*
         * URL کامل
         */
        if (
            preg_match(
                '~^https?://~i',
                $url
            )
        ) {
            return $url;
        }

        /*
         * //example.com
         */
        if (
            str_starts_with(
                $url,
                '//'
            )
        ) {
            $scheme =
                parse_url(
                    $baseUrl,
                    PHP_URL_SCHEME
                ) ?: 'https';

            return $scheme . ':' . $url;
        }

        $base =
            parse_url(
                $baseUrl
            );

        if (!$base) {
            return $url;
        }

        $scheme =
            $base['scheme']
            ?? 'https';

        $host =
            $base['host']
            ?? '';

        $port =
            isset($base['port'])
                ? ':' . $base['port']
                : '';

        if (
            str_starts_with(
                $url,
                '/'
            )
        ) {
            return
                $scheme .
                '://' .
                $host .
                $port .
                $url;
        }

        $path =
            $base['path']
            ?? '/';

        $directory =
            rtrim(
                dirname($path),
                '/'
            );

        return
            $scheme .
            '://' .
            $host .
            $port .
            $directory .
            '/' .
            $url;
    }

    /**
     * شناسه پایدار خبر
     */
    protected function makeExternalId(
        string $url,
        array $item
    ): string {
        /*
         * اگر سایت ID در DOM داشته باشد
         * بعداً می‌توانیم این قسمت را توسعه دهیم.
         */
        return sha1(
            trim($url)
        );
    }

    /**
     * تمیز کردن متن
     */
    protected function cleanText(
        ?string $text
    ): string {
        if (!$text) {
            return '';
        }

        /*
         * NBSP
         */
        $text =
            str_replace(
                "\xc2\xa0",
                ' ',
                $text
            );

        /*
         * Zero-width
         */
        $text =
            str_replace(
                [
                    "\u{200B}",
                    "\u{200D}",
                    "\u{FEFF}",
                ],
                '',
                $text
            );

        /*
         * یکسان‌سازی فاصله
         */
        $text =
            preg_replace(
                '/[ \t]+/u',
                ' ',
                $text
            ) ?? $text;

        /*
         * خطوط خالی زیاد
         */
        $text =
            preg_replace(
                "/\n{3,}/u",
                "\n\n",
                $text
            ) ?? $text;

        /*
         * trim هر خط
         */
        $lines =
            preg_split(
                "/\r\n|\r|\n/u",
                $text
            );

        if (
            is_array($lines)
        ) {
            $lines =
                array_map(
                    static fn ($line) =>
                        trim($line),
                    $lines
                );

            $text =
                implode(
                    "\n",
                    $lines
                );
        }

        return trim($text);
    }

    /**
     * عنوان صفحه بدون ایجاد exception
     */
    protected function safePageTitle(
        mixed $page
    ): ?string {
        try {
            return trim(
                $page->title()
            );
        } catch (Throwable) {
            return null;
        }
    }
}