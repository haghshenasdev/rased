<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Playwright\Playwright;
use Throwable;

class BrowserReader implements SourceReaderInterface
{
    /**
     * تعداد تقریبی خبرهایی که حداکثر می‌خواهیم جمع کنیم.
     */
    private int $maxItems = 30;

    /**
     * تعداد دفعاتی که اگر آیتم جدید پیدا نشد اسکرول ادامه پیدا کند.
     */
    private int $maxEmptyScrolls = 5;

    /**
     * فاصله اسکرول‌ها.
     */
    private int $scrollStep = 900;

    /**
     * زمان انتظار بعد از اسکرول.
     */
    private int $scrollWait = 700;

    /**
     * زمان انتظار اولیه برای رندر شدن صفحه.
     */
    private int $initialWait = 2000;

    /**
     * اجرای Reader
     */
    public function read(Source $source): array
    {
        $context = null;

        try {
            Log::info('BrowserReader started', [
                'source_id' => $source->id,
                'source_name' => $source->name,
                'url' => $source->url,
            ]);

            /*
             * نکته مهم:
             *
             * Playwright::chromium() در نسخه فعلی playwright-php
             * یک BrowserContext برمی‌گرداند.
             *
             * بنابراین نباید روی نتیجه آن newContext() بزنیم.
             */
            $context = Playwright::chromium([
                'headless' => true,

                /*
                 * برای سرور Linux معمولاً این آرگومان‌ها کمک می‌کنند.
                 */
                'args' => [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--no-zygote',
                ],

                /*
                 * تنظیمات context
                 */
                'context' => [
                    'viewport' => [
                        'width' => 1440,
                        'height' => 900,
                    ],

                    'locale' => 'fa-IR',

                    'userAgent' =>
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                        . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                        . 'Chrome/151.0.0.0 Safari/537.36',
                ],
            ]);

            $page = $context->newPage();

            /*
             * مشخص می‌کنیم این Source مربوط به فارس‌نیوز است یا خیر.
             */
            if ($this->isFarsnews($source)) {
                $items = $this->readFarsnews($page, $context, $source);
            } else {
                $items = $this->readGeneric($page, $context, $source);
            }

            Log::info('BrowserReader finished', [
                'source_id' => $source->id,
                'source_name' => $source->name,
                'items_count' => count($items),
            ]);

            return $items;
        } catch (Throwable $e) {
            Log::error('BrowserReader failed', [
                'source_id' => $source->id ?? null,
                'source_name' => $source->name ?? null,
                'url' => $source->url ?? null,

                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            if ($context) {
                try {
                    $context->close();
                } catch (Throwable $e) {
                    Log::warning('Could not close Playwright context', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * تشخیص فارس‌نیوز
     */
    private function isFarsnews(Source $source): bool
    {
        $url = strtolower($source->url ?? '');

        return str_contains($url, 'farsnews.ir');
    }

    /**
     * Reader مخصوص فارس‌نیوز
     */
    private function readFarsnews($page, $context, Source $source): array
    {
        $url = $this->getListUrl($source);

        Log::info('Opening Farsnews search page', [
            'url' => $url,
        ]);

        /*
         * باز کردن صفحه جستجو
         */
        $page->goto($url, [
            'waitUntil' => 'domcontentloaded',
            'timeout' => 60000,
        ]);

        /*
         * کمی زمان برای اجرای JavaScript و React/Svelte/Vue
         */
        $this->sleep($this->initialWait);

        Log::info('Farsnews page loaded', [
            'url' => $page->url(),
        ]);

        /*
         * منتظر اولین blockquote خبر می‌مانیم.
         *
         * طبق HTML رندرشده‌ای که بررسی کردیم:
         *
         * <blockquote
         *     cite="https://farsnews.ir/MaryamKarami/1788294412602594881"
         * >
         *
         * بنابراین cite قابل اعتمادترین راه استخراج URL خبر است.
         */
        try {
            $page->locator(
                'blockquote[cite^="https://farsnews.ir/"]'
            )->first()->waitFor([
                'state' => 'visible',
                'timeout' => 15000,
            ]);
        } catch (Throwable $e) {
            /*
             * ممکن است صفحه هنوز در حال رندر باشد.
             * در این حالت ادامه می‌دهیم و بعد count را بررسی می‌کنیم.
             */
            Log::warning('Farsnews result locator wait failed', [
                'message' => $e->getMessage(),
            ]);
        }

        /*
         * جمع‌آوری خبرها
         */
        $results = $this->collectFarsnewsResults($page);

        Log::info('Farsnews search results collected', [
            'count' => count($results),
        ]);

        /*
         * اگر هیچ نتیجه‌ای پیدا نشد، HTML را ذخیره می‌کنیم.
         * این برای تشخیص تغییر ساختار سایت بسیار مفید است.
         */
        if (count($results) === 0) {
            $this->saveDebugHtml(
                $page,
                'farsnews-no-results'
            );

            throw new \RuntimeException(
                'هیچ نتیجه‌ای از صفحه جستجوی فارس‌نیوز پیدا نشد.'
            );
        }

        /*
         * حالا تک تک خبرها را باز می‌کنیم تا متن کامل خبر را بگیریم.
         */
        $items = [];

        foreach ($results as $index => $result) {
            if (count($items) >= $this->maxItems) {
                break;
            }

            try {
                Log::info('Reading Farsnews article', [
                    'index' => $index + 1,
                    'url' => $result['url'],
                    'title' => $result['title'],
                ]);

                $item = $this->readFarsnewsArticle(
                    $context,
                    $source,
                    $result
                );

                if ($item !== null) {
                    $items[] = $item;
                }
            } catch (Throwable $e) {
                Log::warning('Failed to read Farsnews article', [
                    'url' => $result['url'],
                    'title' => $result['title'],
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $items;
    }

    /**
     * جمع‌آوری نتایج صفحه جستجوی فارس‌نیوز
     *
     * فارس‌نیوز از Virtual List استفاده می‌کند.
     * بنابراین همه خبرها الزاماً همزمان در DOM نیستند.
     */
    private function collectFarsnewsResults($page): array
    {
        $results = [];

        $emptyScrolls = 0;
        $lastCount = 0;

        /*
         * چند مرحله اسکرول می‌کنیم.
         */
        for ($scroll = 0; $scroll < 100; $scroll++) {
            /*
             * خبرهای موجود در DOM فعلی
             */
            $locator = $page->locator(
                'blockquote[cite^="https://farsnews.ir/"]'
            );

            $count = $locator->count();

            for ($i = 0; $i < $count; $i++) {
                try {
                    $post = $locator->nth($i);

                    /*
                     * URL واقعی خبر در cite قرار دارد.
                     */
                    $url = trim(
                        (string) $post->getAttribute('cite')
                    );

                    if ($url === '') {
                        continue;
                    }

                    /*
                     * نرمال‌سازی URL
                     */
                    $url = $this->normalizeUrl($url);

                    /*
                     * عنوان
                     *
                     * طبق HTML:
                     *
                     * .post-contents h3
                     */
                    $title = '';

                    try {
                        $title = trim(
                            (string) $post
                                ->locator('.post-contents h3')
                                ->innerText()
                        );
                    } catch (Throwable $e) {
                        // ignore
                    }

                    /*
                     * اگر عنوان پیدا نشد، fallback
                     */
                    if ($title === '') {
                        try {
                            $title = trim(
                                (string) $post
                                    ->locator('h3')
                                    ->first()
                                    ->innerText()
                            );
                        } catch (Throwable $e) {
                            // ignore
                        }
                    }

                    /*
                     * خلاصه خبر
                     */
                    $summary = '';

                    try {
                        $summary = trim(
                            (string) $post
                                ->locator('.post-contents .n-u1u0l1')
                                ->innerText()
                        );
                    } catch (Throwable $e) {
                        // ignore
                    }

                    /*
                     * اگر قبلاً این URL را دیده‌ایم، رد شو.
                     */
                    if (isset($results[$url])) {
                        continue;
                    }

                    $results[$url] = [
                        'url' => $url,
                        'title' => $this->cleanText($title),
                        'summary' => $this->cleanText($summary),
                    ];

                    if (count($results) >= $this->maxItems) {
                        break 2;
                    }
                } catch (Throwable $e) {
                    Log::debug(
                        'Could not parse Farsnews result',
                        [
                            'message' => $e->getMessage(),
                        ]
                    );
                }
            }

            $currentCount = count($results);

            Log::debug('Farsnews scroll iteration', [
                'iteration' => $scroll,
                'dom_items' => $count,
                'unique_items' => $currentCount,
            ]);

            /*
             * اگر آیتم جدیدی پیدا نشد، شمارنده را زیاد می‌کنیم.
             */
            if ($currentCount === $lastCount) {
                $emptyScrolls++;
            } else {
                $emptyScrolls = 0;
            }

            $lastCount = $currentCount;

            /*
             * اگر چند بار پشت سر هم چیزی پیدا نشد،
             * احتمالاً به انتهای لیست رسیده‌ایم.
             */
            if ($emptyScrolls >= $this->maxEmptyScrolls) {
                break;
            }

            /*
             * اسکرول مرحله‌ای.
             *
             * از scrollTo با مقدار ثابت استفاده می‌کنیم
             * تا Virtual List فارس‌نیوز فعال شود.
             */
            try {
                $page->evaluate(
                    <<<JS
                    () => {
                        window.scrollBy({
                            top: {$this->scrollStep},
                            left: 0,
                            behavior: 'instant'
                        });
                    }
                    JS
                );
            } catch (Throwable $e) {
                /*
                 * بعضی نسخه‌های مرورگر ممکن است behavior=instant
                 * را نپذیرند.
                 */
                $page->evaluate(
                    "window.scrollBy(0, {$this->scrollStep});"
                );
            }

            $this->sleep($this->scrollWait);
        }

        /*
         * آرایه associative را به indexed array تبدیل می‌کنیم.
         */
        return array_values($results);
    }

    /**
     * باز کردن صفحه کامل خبر فارس‌نیوز
     */
    private function readFarsnewsArticle(
        $context,
        Source $source,
        array $result
    ): ?SourceItemData {
        $articlePage = null;

        try {
            $articlePage = $context->newPage();

            $articlePage->goto($result['url'], [
                'waitUntil' => 'domcontentloaded',
                'timeout' => 60000,
            ]);

            /*
             * اجازه می‌دهیم محتوای JavaScript کامل شود.
             */
            $this->sleep(1500);

            /*
             * عنوان
             */
            $title = '';

            $titleSelectors = [
                'h1',
                'article h1',
                'main h1',
            ];

            foreach ($titleSelectors as $selector) {
                try {
                    $text = trim(
                        (string) $articlePage
                            ->locator($selector)
                            ->first()
                            ->innerText()
                    );

                    if ($text !== '') {
                        $title = $text;
                        break;
                    }
                } catch (Throwable $e) {
                    // try next selector
                }
            }

            if ($title === '') {
                $title = $result['title'];
            }

            /*
             * متن کامل خبر
             *
             * چون ساختار داخلی صفحه خبر ممکن است با نسخه فعلی
             * سایت تغییر کند، چند selector را امتحان می‌کنیم.
             */
            $content = '';

            $contentSelectors = [
                'article',
                'main article',
                'main .post-content',
                'main .article-content',
                '[class*="article-content"]',
                '[class*="post-content"]',
                'main',
            ];

            foreach ($contentSelectors as $selector) {
                try {
                    $text = trim(
                        (string) $articlePage
                            ->locator($selector)
                            ->first()
                            ->innerText()
                    );

                    /*
                     * متن خیلی کوتاه احتمالاً container اصلی نیست.
                     */
                    if (mb_strlen($text) > 150) {
                        $content = $text;
                        break;
                    }
                } catch (Throwable $e) {
                    // try next selector
                }
            }

            /*
             * آخرین fallback:
             * کل body صفحه.
             */
            if ($content === '') {
                try {
                    $content = trim(
                        (string) $articlePage
                            ->locator('body')
                            ->innerText()
                    );
                } catch (Throwable $e) {
                    // ignore
                }
            }

            /*
             * تمیز کردن متن
             */
            $content = $this->cleanArticleText(
                $content,
                $title
            );

            /*
             * اگر متن واقعاً پیدا نشد، حداقل خلاصه را نگه می‌داریم.
             */
            if ($content === '') {
                $content = $result['summary'];
            }

            /*
             * تاریخ
             */
            $date = null;

            try {
                $dateText = trim(
                    (string) $articlePage
                        ->locator('time')
                        ->first()
                        ->getAttribute('datetime')
                );

                if ($dateText !== '') {
                    $date = $dateText;
                }
            } catch (Throwable $e) {
                // ignore
            }

            /*
             * در صورت نبود datetime، متن time را بررسی می‌کنیم.
             */
            if ($date === null) {
                try {
                    $dateText = trim(
                        (string) $articlePage
                            ->locator('time')
                            ->first()
                            ->innerText()
                    );

                    if ($dateText !== '') {
                        $date = $dateText;
                    }
                } catch (Throwable $e) {
                    // ignore
                }
            }

            /*
             * ساخت DTO
             *
             * این بخش را با constructor واقعی SourceItemData
             * پروژه خودت تطبیق بده اگر نام فیلدها متفاوت است.
             */
            return new
            SourceItemData(
                externalId: $source->id,
                title: $this->cleanText($title),
                url: $result['url'],
                content: $content ?: null,
                publishedAt: $date,
                rawData: [],
            );
        } finally {
            if ($articlePage) {
                try {
                    $articlePage->close();
                } catch (Throwable $e) {
                    // ignore
                }
            }
        }
    }

    /**
     * Reader عمومی برای سایت‌هایی غیر از فارس‌نیوز
     */
    private function readGeneric($page, $context, Source $source): array
    {
        $page->goto($source->url, [
            'waitUntil' => 'domcontentloaded',
            'timeout' => 60000,
        ]);

        $this->sleep($this->initialWait);

        $title = '';

        try {
            $title = trim(
                (string) $page
                    ->locator('h1')
                    ->first()
                    ->innerText()
            );
        } catch (Throwable $e) {
            // ignore
        }

        /*
         * برای Reader عمومی فعلاً متن صفحه را می‌گیریم.
         */
        $content = '';

        try {
            $content = trim(
                (string) $page
                    ->locator('main')
                    ->first()
                    ->innerText()
            );
        } catch (Throwable $e) {
            // fallback
        }

        if ($content === '') {
            try {
                $content = trim(
                    (string) $page
                        ->locator('body')
                        ->innerText()
                );
            } catch (Throwable $e) {
                // ignore
            }
        }

        if ($title === '') {
            $title = Str::limit($content, 100);
        }

        if ($content === '') {
            return [];
        }

        return [
            new
            SourceItemData(
                externalId: '',
                title: $this->cleanText($title),
                url: $source->url ?: null,
                content: $content ?: null,
                publishedAt: null,
                rawData: null,
            ),
        ];
    }

    /**
     * گرفتن URL لیست از تنظیمات Source
     */
    private function getListUrl(Source $source): string
    {
        /*
         * اگر در settings مقدار list_url تعریف شده باشد،
         * همان را استفاده می‌کنیم.
         */
        $settings = $source->settings ?? [];

        if (
            is_array($settings)
            && !empty($settings['list_url'])
        ) {
            return $settings['list_url'];
        }

        /*
         * در غیر این صورت خود URL منبع.
         */
        return $source->url;
    }

    /**
     * نرمال کردن URL
     */
    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        /*
         * حذف fragment
         */
        $url = preg_replace('/#.*$/', '', $url);

        /*
         * حذف slash انتهایی
         */
        $url = rtrim($url, '/');

        return $url;
    }

    /**
     * تمیز کردن متن معمولی
     */
    private function cleanText(string $text): string
    {
        /*
         * تبدیل NBSP
         */
        $text = str_replace(
            "\xc2\xa0",
            ' ',
            $text
        );

        /*
         * تبدیل line ending
         */
        $text = str_replace(
            ["\r\n", "\r"],
            "\n",
            $text
        );

        /*
         * حذف فاصله‌های اضافی
         */
        $text = preg_replace(
            "/[ \t]+/u",
            ' ',
            $text
        );

        /*
         * بیش از دو خط خالی
         */
        $text = preg_replace(
            "/\n{3,}/u",
            "\n\n",
            $text
        );

        return trim($text);
    }

    /**
     * تمیز کردن متن صفحه خبر
     */
    private function cleanArticleText(
        string $content,
        string $title = ''
    ): string {
        $content = $this->cleanText($content);

        if ($content === '') {
            return '';
        }

        /*
         * حذف title از ابتدای متن،
         * چون معمولاً h1 داخل article نیز وجود دارد.
         */
        if ($title !== '') {
            $normalizedContent = trim($content);
            $normalizedTitle = trim($title);

            if (
                str_starts_with(
                    $normalizedContent,
                    $normalizedTitle
                )
            ) {
                $content = trim(
                    mb_substr(
                        $normalizedContent,
                        mb_strlen($normalizedTitle)
                    )
                );
            }
        }

        /*
         * حذف چند عبارت رایج رابط کاربری.
         *
         * این بخش عمداً محدود است تا محتوای واقعی خبر حذف نشود.
         */
        $noise = [
            'اشتراک‌گذاری',
            'اشتراک گذاری',
            'ارسال',
            'نظر دهید',
            'نظرات',
        ];

        foreach ($noise as $item) {
            $content = preg_replace(
                '/^' . preg_quote($item, '/') . '\s*/u',
                '',
                $content
            );
        }

        return trim($content);
    }

    /**
     * ذخیره HTML برای Debug
     */
    private function saveDebugHtml($page, string $name): void
    {
        try {
            $html = $page->content();

            $directory = storage_path(
                'app/private/browser-debug'
            );

            if (!is_dir($directory)) {
                mkdir(
                    $directory,
                    0775,
                    true
                );
            }

            $file = $directory . '/' .
                $name . '-' .
                date('Y-m-d-H-i-s') .
                '.html';

            file_put_contents(
                $file,
                $html
            );

            /*
             * Screenshot هم ذخیره می‌کنیم.
             */
            try {
                $page->screenshot([
                    'path' => $directory . '/' .
                        $name . '-' .
                        date('Y-m-d-H-i-s') .
                        '.png',
                    'fullPage' => true,
                ]);
            } catch (Throwable $e) {
                Log::warning(
                    'Could not save browser screenshot',
                    [
                        'message' => $e->getMessage(),
                    ]
                );
            }

            Log::info(
                'Browser debug files saved',
                [
                    'html' => $file,
                ]
            );
        } catch (Throwable $e) {
            Log::warning(
                'Could not save browser debug HTML',
                [
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * sleep میلی‌ثانیه‌ای
     */
    private function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
