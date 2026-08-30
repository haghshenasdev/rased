<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class RssReader implements SourceReaderInterface
{
    /**
     * حداقل طول متن قابل قبول.
     */
    protected int $minimumContentLength = 100;

    /**
     * حداکثر طول HTML دریافتی از صفحه خبر.
     */
    protected int $maximumHtmlLength = 5000000;

    /**
     * حداکثر طول متن استخراج‌شده.
     */
    protected int $maximumContentLength = 500000;

    /**
     * Timeout دریافت RSS.
     */
    protected int $rssTimeout = 20;

    /**
     * Timeout دریافت صفحه خبر.
     */
    protected int $articleTimeout = 15;

    /**
     * اجرای Reader
     */
    public function read(Source $source): array
    {
        if (empty($source->url)) {
            throw new RuntimeException(
                "آدرس RSS برای منبع {$source->name} مشخص نشده است."
            );
        }

        $xml = $this->fetch($source->url);

        return $this->parse($xml);
    }

    /**
     * دریافت RSS / Atom
     */
    protected function fetch(string $url): string
    {
        $response = Http::connectTimeout(10)
            ->timeout($this->rssTimeout)
            ->withoutVerifying()
            ->retry(3, 1000)
            ->withHeaders([
                'User-Agent' =>
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                    'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                    'Chrome/139.0.0.0 Safari/537.36',

                'Accept' =>
                    'application/rss+xml, application/atom+xml, ' .
                    'application/xml, text/xml, ' .
                    'text/html;q=0.9, */*;q=0.8',

                'Accept-Language' =>
                    'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',

                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException(
                "HTTP {$response->status()} از {$url}"
            );
        }

        return $response->body();
    }

    /**
     * تشخیص RSS / Atom
     *
     * @return SourceItemData[]
     */
    protected function parse(string $xml): array
    {
        libxml_use_internal_errors(true);

        $rss = simplexml_load_string(
            $xml,
            SimpleXMLElement::class,
            LIBXML_NOCDATA
        );

        if ($rss === false) {
            $errors = libxml_get_errors();

            libxml_clear_errors();

            $message = 'RSS نامعتبر است.';

            if (!empty($errors)) {
                $message .= ' ' . trim($errors[0]->message);
            }

            throw new RuntimeException($message);
        }

        libxml_clear_errors();

        /*
         * RSS 2.0
         */
        if (isset($rss->channel->item)) {
            return $this->parseRssItems(
                $rss->channel->item
            );
        }

        /*
         * بعضی Feedها item را مستقیم دارند.
         */
        if (isset($rss->item)) {
            return $this->parseRssItems(
                $rss->item
            );
        }

        /*
         * Atom
         */
        if (isset($rss->entry)) {
            return $this->parseAtomEntries(
                $rss->entry
            );
        }

        throw new RuntimeException(
            'ساختار RSS/Atom قابل شناسایی نیست.'
        );
    }

    /**
     * پردازش RSS Items
     *
     * @param iterable<SimpleXMLElement> $items
     * @return SourceItemData[]
     */
    protected function parseRssItems(iterable $items): array
    {
        $result = [];

        foreach ($items as $item) {
            try {
                $title = trim(
                    (string) $item->title
                );

                $link = $this->extractRssLink(
                    $item
                );

                $guid = trim(
                    (string) $item->guid
                );

                $pubDate = trim(
                    (string) $item->pubDate
                );

                /*
                 * محتوای RSS
                 *
                 * این محتوا فقط fallback است.
                 */
                $contentData =
                    $this->extractRssContent($item);

                $rssContent =
                    $contentData['content'];

                /*
                 * مهم:
                 *
                 * اگر لینک وجود داشته باشد،
                 * همیشه صفحه خبر را باز می‌کنیم.
                 *
                 * حتی اگر RSS محتوای کامل داشته باشد.
                 */
                $pageContent = null;

                if ($link !== '') {
                    $pageContent =
                        $this->fetchArticleContent(
                            $link,
                            $title
                        );
                }

                /*
                 * اولویت:
                 *
                 * 1. متن صفحه خبر
                 * 2. محتوای RSS
                 */
                $content =
                    $pageContent
                        ?: $rssContent;

                /*
                 * اگر هیچ محتوایی نبود،
                 * حداقل عنوان را داریم.
                 */
                if (!$content) {
                    $content = $title;
                }

                /*
                 * GUID یا لینک
                 */
                $externalId =
                    $guid ?: $link;

                if (!$externalId) {
                    continue;
                }

                /*
                 * تاریخ انتشار
                 */
                $publishedAt = null;

                if ($pubDate !== '') {
                    try {
                        $publishedAt =
                            Carbon::parse($pubDate);
                    } catch (\Throwable) {
                        $publishedAt = null;
                    }
                }

                /*
                 * اطلاعات خام
                 */
                $rawData = [
                    'guid' => $guid,

                    'pubDate' => $pubDate,

                    'rss_content_source' =>
                        $contentData['source'],

                    'description' =>
                        $contentData['description'],

                    'content_encoded' =>
                        $contentData['content_encoded'],

                    'summary' =>
                        $contentData['summary'],

                    /*
                     * آیا صفحه خبر باز شد؟
                     */
                    'article_page_requested' =>
                        $link !== '',

                    /*
                     * آیا متن صفحه با موفقیت
                     * استخراج شد؟
                     */
                    'page_content_loaded' =>
                        $pageContent !== null,

                    /*
                     * منبع نهایی محتوا
                     */
                    'final_content_source' =>
                        $pageContent !== null
                            ? 'article_page'
                            : 'rss',
                ];

                $result[] = new SourceItemData(
                    externalId: $externalId,
                    title: $title,
                    url: $link ?: null,
                    content: $content ?: null,
                    publishedAt: $publishedAt,
                    rawData: $rawData,
                );

            } catch (\Throwable $e) {
                /*
                 * خراب شدن یک Item نباید باعث شود
                 * تمام RSS از کار بیفتد.
                 */
                logger()->warning(
                    'خطا در پردازش یک RSS Item',
                    [
                        'error' =>
                            $e->getMessage(),

                        'title' =>
                            isset($item->title)
                                ? (string) $item->title
                                : null,
                    ]
                );

                continue;
            }
        }

        return $result;
    }

    /**
     * استخراج لینک RSS
     */
    protected function extractRssLink(
        SimpleXMLElement $item
    ): string {
        /*
         * حالت معمول:
         *
         * <link>https://...</link>
         */
        $link = trim(
            (string) $item->link
        );

        if ($link !== '') {
            return $this->normalizeUrl(
                $link
            );
        }

        /*
         * بعضی RSSها link را با href دارند.
         */
        foreach ($item->link as $linkNode) {
            $attributes =
                $linkNode->attributes();

            $href = trim(
                (string) ($attributes['href'] ?? '')
            );

            if ($href !== '') {
                return $this->normalizeUrl(
                    $href
                );
            }
        }

        return '';
    }

    /**
     * استخراج تمام انواع محتوای RSS
     */
    protected function extractRssContent(
        SimpleXMLElement $item
    ): array {
        $description = trim(
            (string) ($item->description ?? '')
        );

        $contentEncoded = '';

        /*
         * content:encoded
         */
        $namespaces =
            $item->getNameSpaces(true);

        if (isset($namespaces['content'])) {
            try {
                $contentNode =
                    $item->children(
                        $namespaces['content']
                    );

                if (isset($contentNode->encoded)) {
                    $contentEncoded =
                        trim(
                            (string) $contentNode->encoded
                        );
                }
            } catch (\Throwable) {
                $contentEncoded = '';
            }
        }

        /*
         * بررسی سایر namespaceها
         */
        if (
            !$contentEncoded &&
            !empty($namespaces)
        ) {
            foreach (
                $namespaces
                as $namespaceName => $namespaceUrl
            ) {
                try {
                    $children =
                        $item->children(
                            $namespaceUrl
                        );

                    foreach (
                        $children as $key => $value
                    ) {
                        $key = strtolower(
                            (string) $key
                        );

                        if (
                            in_array(
                                $key,
                                [
                                    'encoded',
                                    'content',
                                    'fullcontent',
                                    'full_content',
                                    'body',
                                    'article',
                                    'description',
                                ],
                                true
                            )
                        ) {
                            $valueText =
                                trim(
                                    (string) $value
                                );

                            if ($valueText !== '') {
                                $contentEncoded =
                                    $valueText;

                                break 2;
                            }
                        }
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        /*
         * content معمولی
         */
        $content = trim(
            (string) ($item->content ?? '')
        );

        /*
         * summary
         */
        $summary = trim(
            (string) ($item->summary ?? '')
        );

        /*
         * اولویت محتوای RSS
         */
        $candidates = [
            'content_encoded' =>
                $contentEncoded,

            'content' =>
                $content,

            'description' =>
                $description,

            'summary' =>
                $summary,
        ];

        /*
         * بهترین محتوای موجود
         */
        foreach (
            $candidates as $source => $value
        ) {
            if (!$value) {
                continue;
            }

            $cleanText =
                $this->htmlToText($value);

            if (
                mb_strlen(
                    trim($cleanText)
                ) >= $this->minimumContentLength
            ) {
                return [
                    'content' => $value,

                    'source' => $source,

                    'description' =>
                        $description,

                    'content_encoded' =>
                        $contentEncoded,

                    'summary' =>
                        $summary,
                ];
            }
        }

        /*
         * اگر محتوا کوتاه بود،
         * اولین مقدار موجود را برگردان.
         */
        foreach (
            $candidates as $source => $value
        ) {
            if ($value) {
                return [
                    'content' => $value,

                    'source' => $source,

                    'description' =>
                        $description,

                    'content_encoded' =>
                        $contentEncoded,

                    'summary' =>
                        $summary,
                ];
            }
        }

        return [
            'content' => '',

            'source' => null,

            'description' =>
                $description,

            'content_encoded' =>
                $contentEncoded,

            'summary' =>
                $summary,
        ];
    }

    /**
     * Atom Entries
     *
     * @param iterable<SimpleXMLElement> $entries
     * @return SourceItemData[]
     */
    protected function parseAtomEntries(
        iterable $entries
    ): array {
        $result = [];

        foreach ($entries as $entry) {
            try {
                $title = trim(
                    (string) $entry->title
                );

                $link =
                    $this->extractAtomLink(
                        $entry
                    );

                $id = trim(
                    (string) $entry->id
                );

                $published =
                    trim(
                        (string) (
                        $entry->published
                            ?: $entry->updated
                        )
                    );

                /*
                 * محتوای Atom
                 */
                $contentData =
                    $this->extractAtomContent(
                        $entry
                    );

                $rssContent =
                    $contentData['content'];

                /*
                 * مهم:
                 *
                 * برای Atom نیز همیشه صفحه خبر
                 * باز می‌شود.
                 */
                $pageContent = null;

                if ($link !== '') {
                    $pageContent =
                        $this->fetchArticleContent(
                            $link,
                            $title
                        );
                }

                /*
                 * اولویت:
                 *
                 * صفحه خبر
                 * سپس Atom
                 */
                $content =
                    $pageContent
                        ?: $rssContent;

                if (!$content) {
                    $content = $title;
                }

                $externalId =
                    $id ?: $link;

                if (!$externalId) {
                    continue;
                }

                $publishedAt = null;

                if ($published !== '') {
                    try {
                        $publishedAt =
                            Carbon::parse(
                                $published
                            );
                    } catch (\Throwable) {
                        $publishedAt = null;
                    }
                }

                $result[] =
                    new SourceItemData(
                        externalId:
                        $externalId,

                        title:
                        $title,

                        url:
                        $link ?: null,

                        content:
                        $content ?: null,

                        publishedAt:
                        $publishedAt,

                        rawData: [
                            'id' => $id,

                            'published' =>
                                $published,

                            'atom_content_source' =>
                                $contentData['source'],

                            'summary' =>
                                $contentData['summary'],

                            'page_content_loaded' =>
                                $pageContent !== null,

                            'article_page_requested' =>
                                $link !== '',

                            'final_content_source' =>
                                $pageContent !== null
                                    ? 'article_page'
                                    : 'atom',
                        ],
                    );

            } catch (\Throwable $e) {
                logger()->warning(
                    'خطا در پردازش Atom Entry',
                    [
                        'error' =>
                            $e->getMessage(),
                    ]
                );

                continue;
            }
        }

        return $result;
    }

    /**
     * استخراج لینک Atom
     */
    protected function extractAtomLink(
        SimpleXMLElement $entry
    ): string {
        /*
         * namespaceهای Atom
         */
        $namespaces =
            $entry->getNameSpaces(true);

        /*
         * حالت استاندارد:
         *
         * <link href="..." />
         */
        foreach ($entry->link as $link) {
            $attributes =
                $link->attributes();

            $href = trim(
                (string) ($attributes['href'] ?? '')
            );

            if ($href === '') {
                continue;
            }

            $rel = strtolower(
                trim(
                    (string) (
                        $attributes['rel'] ?? ''
                    )
                )
            );

            /*
             * لینک اصلی مقاله
             */
            if (
                $rel === '' ||
                $rel === 'alternate'
            ) {
                return $this->normalizeUrl(
                    $href
                );
            }
        }

        /*
         * بعضی Feedها link را به صورت
         * متن قرار می‌دهند.
         */
        $textLink = trim(
            (string) $entry->link
        );

        if ($textLink !== '') {
            return $this->normalizeUrl(
                $textLink
            );
        }

        return '';
    }

    /**
     * استخراج محتوای Atom
     */
    protected function extractAtomContent(
        SimpleXMLElement $entry
    ): array {
        $content = '';

        $summary = trim(
            (string) ($entry->summary ?? '')
        );

        /*
         * Atom content
         */
        if (isset($entry->content)) {
            $content =
                trim(
                    (string) $entry->content
                );
        }

        /*
         * اولویت content نسبت به summary
         */
        if ($content !== '') {
            return [
                'content' =>
                    $content,

                'source' =>
                    'content',

                'summary' =>
                    $summary,
            ];
        }

        if ($summary !== '') {
            return [
                'content' =>
                    $summary,

                'source' =>
                    'summary',

                'summary' =>
                    $summary,
            ];
        }

        return [
            'content' =>
                '',

            'source' =>
                null,

            'summary' =>
                $summary,
        ];
    }

    /**
     * باز کردن صفحه خبر
     *
     * این متد برای هر خبر جدیدی که لینک دارد
     * فراخوانی می‌شود.
     */
    protected function fetchArticleContent(
        string $url,
        string $title = ''
    ): ?string {
        try {
            $url = $this->normalizeUrl(
                $url
            );

            if ($url === '') {
                return null;
            }

            logger()->debug(
                'باز کردن صفحه خبر RSS',
                [
                    'url' => $url,
                    'title' => $title,
                ]
            );

            $response = Http::connectTimeout(8)
                ->timeout($this->articleTimeout)
                ->withoutVerifying()
                ->retry(
                    2,
                    800,
                    function (
                        $exception,
                        $request
                    ) {
                        return true;
                    }
                )
                ->withHeaders([
                    'User-Agent' =>
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                        'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                        'Chrome/139.0.0.0 Safari/537.36',

                    'Accept' =>
                        'text/html,application/xhtml+xml,' .
                        'application/xml;q=0.9,' .
                        '*/*;q=0.8',

                    'Accept-Language' =>
                        'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',

                    'Cache-Control' =>
                        'no-cache',

                    'Pragma' =>
                        'no-cache',
                ])
                ->get($url);

            if (!$response->successful()) {
                logger()->warning(
                    'صفحه خبر قابل دریافت نیست',
                    [
                        'url' =>
                            $url,

                        'status' =>
                            $response->status(),
                    ]
                );

                return null;
            }

            $html =
                $response->body();

            if (!$html) {
                return null;
            }

            /*
             * جلوگیری از پردازش HTML بسیار بزرگ
             */
            if (
                strlen($html) >
                $this->maximumHtmlLength
            ) {
                $html =
                    substr(
                        $html,
                        0,
                        $this->maximumHtmlLength
                    );
            }

            $content =
                $this->extractArticleFromHtml(
                    $html,
                    $title
                );

            if ($content === null) {
                logger()->debug(
                    'محتوای اصلی صفحه خبر پیدا نشد',
                    [
                        'url' =>
                            $url,
                    ]
                );
            }

            return $content;

        } catch (\Throwable $e) {
            /*
             * شکست یک صفحه نباید باعث شود
             * کل RSS متوقف شود.
             */
            logger()->warning(
                'خطا در باز کردن صفحه خبر RSS',
                [
                    'url' =>
                        $url,

                    'title' =>
                        $title,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return null;
        }
    }

    /**
     * استخراج محتوای اصلی خبر از HTML
     */
    protected function extractArticleFromHtml(
        string $html,
        string $title = ''
    ): ?string {
        if (trim($html) === '') {
            return null;
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        /*
         * تضمین UTF-8
         */
        $htmlForDom =
            '<?xml encoding="UTF-8">' .
            $html;

        $loaded = @$dom->loadHTML(
            $htmlForDom,
            LIBXML_NOERROR |
            LIBXML_NOWARNING |
            LIBXML_NONET
        );

        libxml_clear_errors();

        if (!$loaded) {
            return null;
        }

        $xpath =
            new DOMXPath($dom);

        /*
         * حذف عناصر غیرمتنی
         */
        $removeTags = [
            'script',
            'style',
            'noscript',
            'svg',
            'canvas',
            'iframe',
            'nav',
            'header',
            'footer',
            'aside',
            'form',
            'button',
            'input',
            'select',
            'textarea',
            'video',
            'audio',
            'source',
            'figure',
            'figcaption',
            'template',
            'object',
            'embed',
        ];

        foreach ($removeTags as $tag) {
            $nodes =
                $dom->getElementsByTagName(
                    $tag
                );

            /*
             * NodeList زنده است؛ از آخر حذف می‌کنیم.
             */
            for (
                $i = $nodes->length - 1;
                $i >= 0;
                $i--
            ) {
                $node =
                    $nodes->item($i);

                if (
                    $node &&
                    $node->parentNode
                ) {
                    $node->parentNode
                        ->removeChild($node);
                }
            }
        }

        /*
         * حذف لینک‌های ناخواسته،
         * منوها و عناصر تبلیغاتی بر اساس
         * class/id.
         */
        $this->removeNoiseNodes(
            $xpath,
            $dom
        );

        /*
         * ---------------------------------
         * مرحله ۱
         * JSON-LD
         * ---------------------------------
         */
        $jsonLdContent =
            $this->extractJsonLdArticleBody(
                $xpath
            );

        if (
            $jsonLdContent &&
            $this->isValidArticleContent(
                $jsonLdContent
            )
        ) {
            return $this->limitContent(
                $jsonLdContent
            );
        }

        /*
         * ---------------------------------
         * مرحله ۲
         * تگ article
         * ---------------------------------
         */
        $articleNodes =
            $xpath->query(
                '//article'
            );

        if (
            $articleNodes !== false &&
            $articleNodes->length > 0
        ) {
            $best = null;
            $bestLength = 0;

            foreach ($articleNodes as $node) {
                $text =
                    $this->nodeToText(
                        $node
                    );

                $length =
                    mb_strlen(
                        trim($text)
                    );

                if (
                    $length >
                    $bestLength
                ) {
                    $bestLength =
                        $length;

                    $best =
                        $text;
                }
            }

            if (
                $best &&
                $this->isValidArticleContent(
                    $best
                )
            ) {
                return $this->limitContent(
                    $best
                );
            }
        }

        /*
         * ---------------------------------
         * مرحله ۳
         * main
         * ---------------------------------
         */
        $mainNodes =
            $xpath->query(
                '//main'
            );

        if (
            $mainNodes !== false &&
            $mainNodes->length > 0
        ) {
            $best = null;
            $bestLength = 0;

            foreach ($mainNodes as $node) {
                $text =
                    $this->nodeToText(
                        $node
                    );

                $length =
                    mb_strlen(
                        trim($text)
                    );

                if (
                    $length >
                    $bestLength
                ) {
                    $bestLength =
                        $length;

                    $best =
                        $text;
                }
            }

            if (
                $best &&
                $this->isValidArticleContent(
                    $best
                )
            ) {
                return $this->limitContent(
                    $best
                );
            }
        }

        /*
         * ---------------------------------
         * مرحله ۴
         * selectorهای رایج سایت‌های خبری
         * ---------------------------------
         */
        $selectors = [
            /*
             * کلاس‌ها
             */
            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "article-body"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "article-content"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "post-content"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "entry-content"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "news-content"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "news-body"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "article-text"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "article-detail"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "detail-content"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "story-body"
            )]',

            '//*[contains(
                translate(
                    @class,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "content-body"
            )]',

            /*
             * ID
             */
            '//*[contains(
                translate(
                    @id,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "article"
            )]',

            '//*[contains(
                translate(
                    @id,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "content"
            )]',

            '//*[contains(
                translate(
                    @id,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ),
                "news"
            )]',
        ];

        $best = null;
        $bestScore = 0;

        foreach ($selectors as $selector) {
            $nodes =
                @$xpath->query(
                    $selector
                );

            if (
                $nodes === false ||
                $nodes->length === 0
            ) {
                continue;
            }

            foreach ($nodes as $node) {
                $text =
                    $this->nodeToText(
                        $node
                    );

                $text =
                    trim($text);

                if (
                    !$this->isValidArticleContent(
                        $text
                    )
                ) {
                    continue;
                }

                $score =
                    $this->scoreContentNode(
                        $node,
                        $text,
                        $title
                    );

                if (
                    $score >
                    $bestScore
                ) {
                    $bestScore =
                        $score;

                    $best =
                        $text;
                }
            }
        }

        if (
            $best &&
            $this->isValidArticleContent(
                $best
            )
        ) {
            return $this->limitContent(
                $best
            );
        }

        /*
         * ---------------------------------
         * مرحله ۵
         * پیدا کردن بهترین div
         * ---------------------------------
         */
        $divNodes =
            $xpath->query(
                '//div'
            );

        if (
            $divNodes !== false
        ) {
            $best = null;
            $bestScore = 0;

            foreach ($divNodes as $node) {
                /*
                 * فقط divهایی که تعداد مناسبی
                 * متن دارند.
                 */
                $text =
                    $this->nodeToText(
                        $node
                    );

                $text =
                    trim($text);

                $length =
                    mb_strlen($text);

                if (
                    $length <
                    $this->minimumContentLength
                ) {
                    continue;
                }

                /*
                 * divهای بسیار بزرگ معمولاً
                 * container کل سایت هستند.
                 */
                if ($length > 200000) {
                    continue;
                }

                $score =
                    $this->scoreContentNode(
                        $node,
                        $text,
                        $title
                    );

                if (
                    $score >
                    $bestScore
                ) {
                    $bestScore =
                        $score;

                    $best =
                        $text;
                }
            }

            if (
                $best &&
                $this->isValidArticleContent(
                    $best
                )
            ) {
                return $this->limitContent(
                    $best
                );
            }
        }

        /*
         * ---------------------------------
         * مرحله ۶
         * fallback کل body
         * ---------------------------------
         */
        $bodyNodes =
            $xpath->query(
                '//body'
            );

        if (
            $bodyNodes !== false &&
            $bodyNodes->length > 0
        ) {
            $text =
                $this->nodeToText(
                    $bodyNodes->item(0)
                );

            if (
                $this->isValidArticleContent(
                    $text
                )
            ) {
                return $this->limitContent(
                    $text
                );
            }
        }

        return null;
    }

    /**
     * حذف عناصر تبلیغاتی و غیرمفید
     */
    protected function removeNoiseNodes(
        DOMXPath $xpath,
        DOMDocument $dom
    ): void {
        /*
         * XPath برای class و id.
         */
        $patterns = [
            'advert',
            'advertisement',
            'ads',
            'ad-container',
            'banner',
            'popup',
            'modal',
            'cookie',
            'social',
            'share',
            'related',
            'recommend',
            'recommended',
            'sidebar',
            'breadcrumb',
            'comments',
            'comment',
            'navigation',
            'menu',
            'footer',
            'header',
            'telegram',
            'instagram',
            'twitter',
            'facebook',
        ];

        foreach ($patterns as $pattern) {
            $patternLower =
                strtolower($pattern);

            $query =
                '//*[contains(' .
                'translate(@class,' .
                '"ABCDEFGHIJKLMNOPQRSTUVWXYZ",' .
                '"abcdefghijklmnopqrstuvwxyz"' .
                '), ' .
                '"' . $patternLower . '"' .
                ')]';

            $nodes =
                @$xpath->query(
                    $query
                );

            if (
                $nodes === false
            ) {
                continue;
            }

            /*
             * اگر selector خیلی عمومی بود،
             * فقط عناصر مشخص را حذف می‌کنیم.
             */
            for (
                $i = $nodes->length - 1;
                $i >= 0;
                $i--
            ) {
                $node =
                    $nodes->item($i);

                if (
                    !$node ||
                    !$node->parentNode
                ) {
                    continue;
                }

                /*
                 * اگر body یا html بود حذف نکن.
                 */
                if (
                    $node->nodeName === 'body' ||
                    $node->nodeName === 'html'
                ) {
                    continue;
                }

                $node->parentNode
                    ->removeChild($node);
            }
        }
    }

    /**
     * استخراج articleBody از JSON-LD
     */
    protected function extractJsonLdArticleBody(
        DOMXPath $xpath
    ): ?string {
        $nodes =
            $xpath->query(
                '//script[
                    contains(
                        translate(
                            @type,
                            "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                            "abcdefghijklmnopqrstuvwxyz"
                        ),
                        "ld+json"
                    )
                ]'
            );

        if (
            $nodes === false ||
            $nodes->length === 0
        ) {
            return null;
        }

        $best = null;
        $bestLength = 0;

        foreach ($nodes as $node) {
            $json =
                trim(
                    $node->textContent
                );

            if ($json === '') {
                continue;
            }

            /*
             * گاهی JSON-LD با escapeهای عجیب
             * همراه است.
             */
            $data =
                json_decode(
                    $json,
                    true
                );

            if (
                !is_array($data)
            ) {
                continue;
            }

            $bodies =
                $this->findArticleBodiesInJsonLd(
                    $data
                );

            foreach ($bodies as $body) {
                $body =
                    $this->htmlToText(
                        $body
                    );

                $body =
                    trim($body);

                $length =
                    mb_strlen($body);

                if (
                    $length >
                    $bestLength
                ) {
                    $bestLength =
                        $length;

                    $best =
                        $body;
                }
            }
        }

        return $best;
    }

    /**
     * جستجوی recursive برای articleBody
     */
    protected function findArticleBodiesInJsonLd(
        mixed $data
    ): array {
        $result = [];

        if (
            !is_array($data)
        ) {
            return $result;
        }

        /*
         * articleBody مستقیم
         */
        if (
            isset($data['articleBody']) &&
            is_string($data['articleBody'])
        ) {
            $result[] =
                $data['articleBody'];
        }

        /*
         * گاهی @graph داریم.
         */
        foreach (
            $data as $key => $value
        ) {
            if (
                is_array($value)
            ) {
                $result =
                    array_merge(
                        $result,
                        $this->findArticleBodiesInJsonLd(
                            $value
                        )
                    );
            }
        }

        return $result;
    }

    /**
     * تبدیل Node به متن تمیز
     */
    protected function nodeToText(
        ?DOMNode $node
    ): string {
        if (!$node) {
            return '';
        }

        /*
         * ابتدا innerHTML را به متن تبدیل می‌کنیم.
         */
        $html =
            '';

        foreach (
            $node->childNodes as $child
        ) {
            $html .=
                $node->ownerDocument
                    ? $node->ownerDocument
                    ->saveHTML($child)
                    : $child->textContent;
        }

        if ($html === '') {
            $html =
                $node->textContent;
        }

        return $this->htmlToText(
            $html
        );
    }

    /**
     * تبدیل HTML به متن قابل جستجو
     */
    protected function htmlToText(
        ?string $html
    ): string {
        if (!$html) {
            return '';
        }

        /*
         * حذف script/style
         */
        $html =
            preg_replace(
                '~<script\b[^>]*>.*?</script>~is',
                ' ',
                $html
            ) ?? $html;

        $html =
            preg_replace(
                '~<style\b[^>]*>.*?</style>~is',
                ' ',
                $html
            ) ?? $html;

        /*
         * بعضی تگ‌ها باید newline ایجاد کنند.
         */
        $html =
            preg_replace(
                '~<(br|p|div|li|article|section|h[1-6]|blockquote|tr)[^>]*>~i',
                "\n",
                $html
            ) ?? $html;

        $html =
            preg_replace(
                '~</(p|div|li|article|section|h[1-6]|blockquote|tr)>~i',
                "\n",
                $html
            ) ?? $html;

        /*
         * HTML entity
         */
        $text =
            html_entity_decode(
                strip_tags($html),
                ENT_QUOTES |
                ENT_HTML5,
                'UTF-8'
            );

        /*
         * حذف کاراکترهای نامرئی
         */
        $text =
            str_replace(
                [
                    "\xC2\xA0",
                    "\u{200B}",
                    "\u{200C}",
                    "\u{200D}",
                    "\u{FEFF}",
                ],
                [
                    ' ',
                    '',
                    '‌',
                    '',
                    '',
                ],
                $text
            );

        /*
         * یکسان‌سازی خطوط
         */
        $text =
            preg_replace(
                "/[ \t]+/u",
                ' ',
                $text
            ) ?? $text;

        $text =
            preg_replace(
                "/\n[ \t]+/u",
                "\n",
                $text
            ) ?? $text;

        $text =
            preg_replace(
                "/[ \t]+\n/u",
                "\n",
                $text
            ) ?? $text;

        /*
         * بیشتر از دو خط خالی نداشته باشیم.
         */
        $text =
            preg_replace(
                "/\n{3,}/u",
                "\n\n",
                $text
            ) ?? $text;

        return trim($text);
    }

    /**
     * امتیازدهی به یک Node
     */
    protected function scoreContentNode(
        DOMNode $node,
        string $text,
        string $title = ''
    ): float {
        $length =
            mb_strlen($text);

        if ($length < 1) {
            return 0;
        }

        /*
         * پایه امتیاز بر اساس طول متن.
         */
        $score =
            min(
                $length / 100,
                1000
            );

        /*
         * تراکم پاراگراف.
         */
        $paragraphCount = 0;

        if ($node instanceof DOMElement) {
            $paragraphs =
                $node->getElementsByTagName(
                    'p'
                );

            $paragraphCount =
                $paragraphs->length;
        }

        $score +=
            min(
                $paragraphCount * 8,
                200
            );

        /*
         * اگر title داخل متن وجود داشته باشد،
         * احتمالاً container خبر است.
         */
        if (
            $title !== '' &&
            mb_stripos(
                $text,
                $title
            ) !== false
        ) {
            $score += 50;
        }

        /*
         * نسبت حروف به طول کل.
         */
        $letters =
            preg_match_all(
                '/[\p{L}\p{N}]/u',
                $text,
                $matches
            );

        if (
            $letters !== false &&
            $length > 0
        ) {
            $ratio =
                $letters / $length;

            if ($ratio > 0.45) {
                $score += 100;
            }
        }

        /*
         * متن‌هایی که بیش از حد بزرگ هستند
         * احتمالاً container کل صفحه‌اند.
         */
        if ($length > 100000) {
            $score *= 0.35;
        } elseif ($length > 50000) {
            $score *= 0.65;
        }

        return $score;
    }

    /**
     * بررسی اینکه متن احتمالاً متن خبر است.
     */
    protected function isValidArticleContent(
        ?string $text
    ): bool {
        if (!$text) {
            return false;
        }

        $text =
            trim($text);

        $length =
            mb_strlen($text);

        if (
            $length <
            $this->minimumContentLength
        ) {
            return false;
        }

        /*
         * حداقل چند کلمه.
         */
        $wordCount =
            preg_match_all(
                '/\S+/u',
                $text,
                $matches
            );

        if (
            $wordCount !== false &&
            $wordCount < 15
        ) {
            return false;
        }

        /*
         * متن‌هایی که تقریباً فقط URL هستند
         * یا متن بسیار غیرطبیعی دارند.
         */
        $letters =
            preg_match_all(
                '/[\p{L}]/u',
                $text,
                $matches
            );

        if (
            $letters !== false &&
            $letters < 50
        ) {
            return false;
        }

        return true;
    }

    /**
     * محدود کردن طول متن.
     */
    protected function limitContent(
        string $content
    ): string {
        $content =
            trim($content);

        if (
            mb_strlen($content) <=
            $this->maximumContentLength
        ) {
            return $content;
        }

        return trim(
                mb_substr(
                    $content,
                    0,
                    $this->maximumContentLength
                )
            ) .
            "\n\n[...]";
    }

    /**
     * نرمال‌سازی URL
     */
    protected function normalizeUrl(
        ?string $url
    ): string {
        if (!$url) {
            return '';
        }

        $url =
            trim($url);

        if ($url === '') {
            return '';
        }

        /*
         * حذف whitespaceهای مخفی
         */
        $url =
            preg_replace(
                '/\s+/u',
                '',
                $url
            ) ?? $url;

        /*
         * فقط HTTP/HTTPS
         */
        $scheme =
            parse_url(
                $url,
                PHP_URL_SCHEME
            );

        if (
            $scheme &&
            !in_array(
                strtolower($scheme),
                [
                    'http',
                    'https',
                ],
                true
            )
        ) {
            return '';
        }

        return $url;
    }
}
