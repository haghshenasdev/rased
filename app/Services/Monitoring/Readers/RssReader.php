<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class RssReader implements SourceReaderInterface
{
    /**
     * حداقل طول متن قابل قبول برای محتوای RSS
     */
    protected int $minimumContentLength = 100;

    /**
     * حداکثر طول محتوایی که از صفحه خبر می‌خوانیم.
     *
     * برای جلوگیری از ذخیره صفحات بسیار بزرگ.
     */
    protected int $maximumContentLength = 500000;

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
     * دریافت محتوا از URL
     */
    protected function fetch(string $url): string
    {
        $response = Http::timeout(30)
            ->withoutVerifying()
            ->retry(3, 1500)
            ->withHeaders([
                'User-Agent' =>
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                    'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                    'Chrome/139.0.0.0 Safari/537.36',

                'Accept' =>
                    'text/html,application/xhtml+xml,' .
                    'application/xml;q=0.9,*/*;q=0.8',

                'Accept-Language' =>
                    'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',

                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Connection' => 'keep-alive',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException(
                "HTTP {$response->status()} از {$url}\n\n" .
                $response->body()
            );
        }

        return $response->body();
    }

    /**
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
            return $this->parseRssItems($rss->channel->item);
        }

        /*
         * RSS/Feedهایی که item مستقیماً دارند
         */
        if (isset($rss->item)) {
            return $this->parseRssItems($rss->item);
        }

        /*
         * Atom
         */
        if (isset($rss->entry)) {
            return $this->parseAtomEntries($rss->entry);
        }

        throw new RuntimeException(
            'ساختار RSS/Atom قابل شناسایی نیست.'
        );
    }

    /**
     * RSS Items
     *
     * @param iterable<SimpleXMLElement> $items
     * @return SourceItemData[]
     */
    protected function parseRssItems(iterable $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $title = trim((string) $item->title);

            $link = trim((string) $item->link);

            $guid = trim((string) $item->guid);

            $pubDate = trim((string) $item->pubDate);

            /*
             * پیدا کردن تمام انواع محتوای RSS
             */
            $contentData = $this->extractRssContent($item);

            $content = $contentData['content'];

            /*
             * اگر RSS متن مناسب نداشت،
             * صفحه اصلی خبر را باز می‌کنیم.
             */
            $pageContent = null;

            if (
                $link &&
                $this->shouldFetchArticlePage($content)
            ) {
                $pageContent = $this->fetchArticleContent(
                    $link,
                    $title
                );

                if ($pageContent) {
                    $content = $pageContent;
                }
            }

            /*
             * اگر GUID وجود نداشت،
             * لینک را به عنوان شناسه استفاده می‌کنیم.
             */
            $externalId = $guid ?: $link;

            if (!$externalId) {
                continue;
            }

            $publishedAt = null;

            if ($pubDate) {
                try {
                    $publishedAt = Carbon::parse($pubDate);
                } catch (\Throwable) {
                    $publishedAt = null;
                }
            }

            $result[] = new SourceItemData(
                externalId: $externalId,
                title: $title,
                url: $link ?: null,
                content: $content ?: null,
                publishedAt: $publishedAt,
                rawData: [
                    'guid' => $guid,
                    'pubDate' => $pubDate,

                    /*
                     * نگه داشتن تمام فیلدهای پیدا شده
                     * برای دیباگ و بررسی بعدی
                     */
                    'rss_content_source' =>
                        $contentData['source'],

                    'description' =>
                        $contentData['description'],

                    'content_encoded' =>
                        $contentData['content_encoded'],

                    'summary' =>
                        $contentData['summary'],

                    'page_content_loaded' =>
                        $pageContent !== null,
                ],
            );
        }

        return $result;
    }

    /**
     * استخراج محتوای RSS از تمام ساختارهای رایج
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
        $namespaces = $item->getNameSpaces(true);

        if (isset($namespaces['content'])) {
            try {
                $contentNode = $item->children(
                    $namespaces['content']
                );

                if (isset($contentNode->encoded)) {
                    $contentEncoded = trim(
                        (string) $contentNode->encoded
                    );
                }
            } catch (\Throwable) {
                $contentEncoded = '';
            }
        }

        /*
         * بعضی RSSها namespace را با نام متفاوت تعریف می‌کنند.
         *
         * بنابراین همه namespaceها را بررسی می‌کنیم.
         */
        if (!$contentEncoded && !empty($namespaces)) {
            foreach ($namespaces as $namespaceName => $namespaceUrl) {
                try {
                    $children = $item->children(
                        $namespaceUrl
                    );

                    foreach ($children as $key => $value) {
                        $key = strtolower((string) $key);

                        if (
                            in_array(
                                $key,
                                [
                                    'encoded',
                                    'content',
                                    'fullcontent',
                                    'body',
                                ],
                                true
                            )
                        ) {
                            $valueText = trim(
                                (string) $value
                            );

                            if ($valueText) {
                                $contentEncoded = $valueText;
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
         * اولویت:
         *
         * content:encoded
         * content
         * description
         * summary
         */
        $candidates = [
            'content_encoded' => $contentEncoded,
            'content' => $content,
            'description' => $description,
            'summary' => $summary,
        ];

        foreach ($candidates as $source => $value) {
            if (!$value) {
                continue;
            }

            $cleanText = $this->htmlToText($value);

            if (
                mb_strlen(
                    trim($cleanText)
                ) >= $this->minimumContentLength
            ) {
                return [
                    'content' => $value,
                    'source' => $source,

                    'description' => $description,
                    'content_encoded' => $contentEncoded,
                    'summary' => $summary,
                ];
            }
        }

        /*
         * حتی اگر متن کوتاه باشد، اولین مقدار موجود را
         * برمی‌گردانیم.
         */
        foreach ($candidates as $source => $value) {
            if ($value) {
                return [
                    'content' => $value,
                    'source' => $source,

                    'description' => $description,
                    'content_encoded' => $contentEncoded,
                    'summary' => $summary,
                ];
            }
        }

        return [
            'content' => '',
            'source' => null,

            'description' => $description,
            'content_encoded' => $contentEncoded,
            'summary' => $summary,
        ];
    }

    /**
     * تشخیص اینکه آیا باید صفحه خبر را باز کنیم یا نه.
     */
    protected function shouldFetchArticlePage(
        ?string $content
    ): bool {
        if (!$content) {
            return true;
        }

        $text = $this->htmlToText($content);

        /*
         * اگر متن RSS خیلی کوتاه باشد،
         * احتمالاً فقط خلاصه خبر است.
         */
        if (
            mb_strlen(trim($text))
            < $this->minimumContentLength
        ) {
            return true;
        }

        /*
         * اگر متن فقط چند کلمه باشد.
         */
        $wordCount = preg_match_all(
            '/\S+/u',
            $text,
            $matches
        );

        if ($wordCount !== false && $wordCount < 20) {
            return true;
        }

        return false;
    }

    /**
     * باز کردن صفحه اصلی خبر و پیدا کردن متن خبر
     */
    protected function fetchArticleContent(
        string $url,
        string $title = ''
    ): ?string {
        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->retry(2, 1000)
                ->withHeaders([
                    'User-Agent' =>
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                        'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                        'Chrome/139.0.0.0 Safari/537.36',

                    'Accept' =>
                        'text/html,application/xhtml+xml,' .
                        'application/xml;q=0.9,*/*;q=0.8',

                    'Accept-Language' =>
                        'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            if (!$html) {
                return null;
            }

            return $this->extractArticleFromHtml(
                $html,
                $title
            );
        } catch (\Throwable) {
            /*
             * خراب شدن یک صفحه نباید باعث شود
             * کل خواندن RSS متوقف شود.
             */
            return null;
        }
    }

    /**
     * پیدا کردن محتوای اصلی خبر از HTML
     */
    protected function extractArticleFromHtml(
        string $html,
        string $title = ''
    ): ?string {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        /*
         * UTF-8
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

        $xpath = new DOMXPath($dom);

        /*
         * تگ‌هایی که تقریباً هیچ وقت بخشی از متن خبر نیستند.
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
            'figure',
            'figcaption',
        ];

        foreach ($removeTags as $tag) {
            $nodes = $dom->getElementsByTagName($tag);

            /*
             * چون NodeList زنده است، از آخر حذف می‌کنیم.
             */
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);

                if ($node?->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        /*
         * ابتدا ساختارهای بسیار واضح را امتحان می‌کنیم.
         */
        $selectors = [
            '//article',
            '//main',

            /*
             * کلاس‌های رایج محتوای خبر
             */
            "//*[contains(translate(@class,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz'),
                'article-content')]",

            "//*[contains(translate(@class,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz'),
                'article-body')]",

            "//*[contains(translate(@class,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz'),
                'post-content')]",

            "//*[contains(translate(@class,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz'),
                'entry-content')]",

            "//*[contains(translate(@class,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz'),
                'news-content')]",

            "//*[contains(translate(@class,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz'),
                'news-body')]",

            "//*[contains(translate(@class,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz'),
                'content-body')]",

            "//*[contains(translate(@class,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz'),
                'article-text')]",

            "//*[contains(translate(@class,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz'),
                'post-body')]",
        ];

        $bestCandidate = null;
        $bestScore = 0;

        foreach ($selectors as $selector) {
            try {
                $nodes = $xpath->query($selector);

                if (!$nodes) {
                    continue;
                }

                foreach ($nodes as $node) {
                    if (!$node instanceof DOMNode) {
                        continue;
                    }

                    $text = $this->nodeToText($node);

                    $score = $this->scoreArticleCandidate(
                        $node,
                        $text,
                        $title
                    );

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestCandidate = $node;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        /*
         * اگر article/main پیدا شد، همان را استفاده می‌کنیم.
         */
        if ($bestCandidate) {
            $text = $this->nodeToText(
                $bestCandidate
            );

            if (
                mb_strlen(trim($text))
                >= $this->minimumContentLength
            ) {
                return $this->limitContent(
                    $text
                );
            }
        }

        /*
         * اگر ساختار مشخص نبود،
         * بین تمام divها دنبال بهترین کاندید می‌گردیم.
         */
        $divs = $xpath->query('//div');

        if ($divs) {
            foreach ($divs as $div) {
                if (!$div instanceof DOMElement) {
                    continue;
                }

                $text = $this->nodeToText($div);

                if (
                    mb_strlen(trim($text))
                    < $this->minimumContentLength
                ) {
                    continue;
                }

                $score = $this->scoreArticleCandidate(
                    $div,
                    $text,
                    $title
                );

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCandidate = $div;
                }
            }
        }

        if ($bestCandidate) {
            $text = $this->nodeToText(
                $bestCandidate
            );

            if (
                mb_strlen(trim($text))
                >= $this->minimumContentLength
            ) {
                return $this->limitContent(
                    $text
                );
            }
        }

        /*
         * آخرین fallback:
         * کل body
         */
        $body = $xpath->query('//body');

        if ($body && $body->length > 0) {
            $text = $this->nodeToText(
                $body->item(0)
            );

            if (
                mb_strlen(trim($text))
                >= $this->minimumContentLength
            ) {
                return $this->limitContent(
                    $text
                );
            }
        }

        return null;
    }

    /**
     * امتیازدهی به یک node برای تشخیص اینکه
     * محتوای اصلی خبر است یا نه.
     */
    protected function scoreArticleCandidate(
        DOMNode $node,
        string $text,
        string $title = ''
    ): int {
        $length = mb_strlen(
            trim($text)
        );

        if ($length < $this->minimumContentLength) {
            return 0;
        }

        /*
         * طول متن امتیاز مثبت دارد.
         */
        $score = min(
            500,
            (int) ($length / 100)
        );

        if ($node instanceof DOMElement) {
            $class = strtolower(
                $node->getAttribute('class')
            );

            $id = strtolower(
                $node->getAttribute('id')
            );

            $identifier =
                $class . ' ' . $id;

            /*
             * کلمات مثبت
             */
            $positiveWords = [
                'article',
                'content',
                'post',
                'news',
                'story',
                'entry',
                'body',
                'main',
                'text',
            ];

            foreach ($positiveWords as $word) {
                if (
                    str_contains(
                        $identifier,
                        $word
                    )
                ) {
                    $score += 100;
                }
            }

            /*
             * کلمات منفی
             */
            $negativeWords = [
                'sidebar',
                'menu',
                'navigation',
                'nav',
                'footer',
                'header',
                'comment',
                'comments',
                'related',
                'advert',
                'ads',
                'social',
                'share',
                'breadcrumb',
                'login',
                'register',
            ];

            foreach ($negativeWords as $word) {
                if (
                    str_contains(
                        $identifier,
                        $word
                    )
                ) {
                    $score -= 150;
                }
            }
        }

        /*
         * اگر عنوان خبر داخل متن candidate باشد،
         * احتمال اینکه خود خبر باشد بیشتر است.
         */
        if (
            $title &&
            mb_strlen($title) >= 10 &&
            mb_stripos($text, $title) !== false
        ) {
            $score += 250;
        }

        /*
         * تعداد پاراگراف‌ها.
         * متن خبر معمولاً چندین p دارد.
         */
        if ($node instanceof DOMElement) {
            $paragraphs =
                $node->getElementsByTagName('p');

            $paragraphCount =
                $paragraphs->length;

            $score += min(
                300,
                $paragraphCount * 20
            );
        }

        return $score;
    }

    /**
     * تبدیل یک Node به متن تمیز
     */
    protected function nodeToText(
        ?DOMNode $node
    ): string {
        if (!$node) {
            return '';
        }

        $text = $node->textContent ?? '';

        return $this->cleanText($text);
    }

    /**
     * تبدیل HTML به متن ساده
     */
    protected function htmlToText(
        string $html
    ): string {
        if (!$html) {
            return '';
        }

        /*
         * تبدیل br و paragraph به newline
         */
        $html = preg_replace(
            '/<\s*br\s*\/?>/i',
            "\n",
            $html
        );

        $html = preg_replace(
            '/<\s*\/p\s*>/i',
            "\n\n",
            $html
        );

        $html = preg_replace(
            '/<\s*\/div\s*>/i',
            "\n",
            $html
        );

        /*
         * حذف HTML
         */
        $text = strip_tags(
            $html
        );

        /*
         * Decode کردن entityها
         */
        $text = html_entity_decode(
            $text,
            ENT_QUOTES |
            ENT_HTML5,
            'UTF-8'
        );

        return $this->cleanText($text);
    }

    /**
     * تمیز کردن متن
     */
    protected function cleanText(
        string $text
    ): string {
        /*
         * تبدیل NBSP
         */
        $text = str_replace(
            "\xc2\xa0",
            ' ',
            $text
        );

        $text = str_replace(
            "\u{00A0}",
            ' ',
            $text
        );

        /*
         * حذف فاصله‌های اضافی
         */
        $text = preg_replace(
            '/[ \t]+/u',
            ' ',
            $text
        );

        /*
         * حذف خطوط خالی متوالی
         */
        $text = preg_replace(
            "/\n\s*\n\s*\n+/u",
            "\n\n",
            $text
        );

        /*
         * trim هر خط
         */
        $lines = preg_split(
            "/\r\n|\r|\n/u",
            $text
        );

        $cleanLines = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line !== '') {
                $cleanLines[] = $line;
            }
        }

        return trim(
            implode("\n", $cleanLines)
        );
    }

    /**
     * محدود کردن حجم محتوا
     */
    protected function limitContent(
        string $content
    ): string {
        if (
            mb_strlen($content)
            <= $this->maximumContentLength
        ) {
            return $content;
        }

        return mb_substr(
            $content,
            0,
            $this->maximumContentLength
        );
    }

    /**
     * @param iterable<SimpleXMLElement> $entries
     * @return SourceItemData[]
     */
    protected function parseAtomEntries(
        iterable $entries
    ): array {
        $result = [];

        foreach ($entries as $entry) {
            $title = trim(
                (string) $entry->title
            );

            $id = trim(
                (string) $entry->id
            );

            /*
             * Atom content
             */
            $content = trim(
                (string) $entry->content
            );

            /*
             * Atom summary
             */
            $summary = trim(
                (string) $entry->summary
            );

            /*
             * اگر content وجود نداشت summary
             */
            if (!$content) {
                $content = $summary;
            }

            $url = null;

            if (isset($entry->link)) {
                foreach ($entry->link as $link) {
                    $attributes =
                        $link->attributes();

                    if (
                        isset($attributes['rel']) &&
                        (string) $attributes['rel'] === 'alternate'
                    ) {
                        $url = trim(
                            (string) $attributes['href']
                        );

                        break;
                    }

                    if (
                        !$url &&
                        isset($attributes['href'])
                    ) {
                        $url = trim(
                            (string) $attributes['href']
                        );
                    }
                }
            }

            /*
             * اگر Atom متن مناسب نداشت،
             * خود صفحه خبر را باز می‌کنیم.
             */
            $pageContent = null;

            if (
                $url &&
                $this->shouldFetchArticlePage($content)
            ) {
                $pageContent =
                    $this->fetchArticleContent(
                        $url,
                        $title
                    );

                if ($pageContent) {
                    $content = $pageContent;
                }
            }

            $published = trim(
                (string) $entry->published
            );

            if (!$published) {
                $published = trim(
                    (string) $entry->updated
                );
            }

            $publishedAt = null;

            if ($published) {
                try {
                    $publishedAt =
                        Carbon::parse($published);
                } catch (\Throwable) {
                    $publishedAt = null;
                }
            }

            $externalId =
                $id ?: $url;

            if (!$externalId) {
                continue;
            }

            $result[] = new SourceItemData(
                externalId: $externalId,
                title: $title,
                url: $url,
                content: $content ?: null,
                publishedAt: $publishedAt,
                rawData: [
                    'id' => $id,

                    'published' => $published,

                    'updated' =>
                        (string) $entry->updated,

                    'summary' => $summary,

                    'page_content_loaded' =>
                        $pageContent !== null,
                ],
            );
        }

        return $result;
    }
}
