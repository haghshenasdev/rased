<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EitaaReader implements SourceReaderInterface
{
    protected int $maxAttempts = 5;

    protected int $retryDelay = 3000;

    /**
     * @return SourceItemData[]
     */
    public function read(Source $source): array
    {
        $channel = trim((string) $source->identifier);

        if ($channel === '') {
            throw new RuntimeException(
                "شناسه کانال ایتا برای {$source->name} مشخص نشده است."
            );
        }

        /*
         * آیا این اولین اجرای این Source است؟
         */
        $isFirstRun = empty($source->last_item_id);

        $lastReadId = $isFirstRun
            ? 0
            : (int) $source->last_item_id;

        $currentId = null;

        $newPosts = [];

        do {
            $url = 'https://eitaa.com/' . $channel;

            if ($currentId !== null) {
                $url .= '?before=' . $currentId;
            }

            $posts = $this->fetchPosts($url);

            if (empty($posts)) {
                break;
            }

            /*
             * IDها را مرتب می‌کنیم.
             */
            krsort($posts, SORT_NUMERIC);

            $foundNew = false;

            foreach ($posts as $id => $post) {

                $id = (int) $id;

                if ($id <= 0) {
                    continue;
                }

                /*
                 * اولین اجرا:
                 * همه پست‌های صفحه جدید محسوب می‌شوند.
                 *
                 * اجراهای بعدی:
                 * فقط پست‌های بزرگ‌تر از last_item_id.
                 */
                if (!$isFirstRun && $id <= $lastReadId) {
                    continue;
                }

                $foundNew = true;

                $newPosts[$id] = $post;
            }

            /*
             * اگر اولین اجرا است و فقط می‌خواهیم
             * آخرین صفحه را بخوانیم، همینجا متوقف می‌شویم.
             *
             * اگر می‌خواهی کل تاریخچه کانال در اولین اجرا
             * خوانده شود، این شرط را حذف می‌کنیم.
             */
            if ($isFirstRun) {
                break;
            }

            /*
             * قدیمی‌ترین پست این صفحه
             */
            $firstId = array_key_first($posts);

            if ($firstId === null) {
                break;
            }

            $currentId = (int) $firstId;

            /*
             * اگر در این صفحه هیچ پست جدیدی نبود،
             * دیگر نیازی به رفتن به صفحات قدیمی‌تر نیست.
             */
            if (!$foundNew) {
                break;
            }

        } while ($currentId !== null);

        /*
         * حذف پست‌های تکراری
         */
        $newPosts = $this->removeDuplicateTitles($newPosts);

        /*
         * قدیمی → جدید
         */
        ksort($newPosts, SORT_NUMERIC);

        $result = [];

        foreach ($newPosts as $id => $post) {

            $text = $this->cleanText(
                $post['text'] ?? ''
            );

            if ($text === '') {
                continue;
            }

            $date = $post['date'] ?? null;

            $publishedAt = null;

            if ($date) {
                try {
                    $publishedAt = Carbon::parse($date);
                } catch (\Throwable) {
                    $publishedAt = null;
                }
            }

            $result[] = new SourceItemData(
                externalId: (string) $id,

                title: $text,

                url: 'https://eitaa.com/' .
                $channel .
                '/' .
                $id,

                content: $text,

                publishedAt: $publishedAt,

                rawData: [
                    'channel' => $channel,
                    'post_id' => $id,
                    'datetime' => $date,
                ],
            );
        }

        return $result;
    }

    /**
     * دریافت پست‌های یک صفحه
     */
    protected function fetchPosts(string $url): array
    {
        for (
            $attempt = 1;
            $attempt <= $this->maxAttempts;
            $attempt++
        ) {
            try {

                $response = Http::timeout(30)
                    ->withoutVerifying()
                    ->withHeaders([
                        'User-Agent' =>
                            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                            'AppleWebKit/537.36 ' .
                            '(KHTML, like Gecko) ' .
                            'Chrome/139.0 Safari/537.36',

                        'Accept' =>
                            'text/html,application/xhtml+xml,' .
                            'application/xml;q=0.9,*/*;q=0.8',

                        'Accept-Language' =>
                            'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
                    ])
                    ->get($url);

                if ($response->successful()) {

                    return $this->parseHtml(
                        $response->body()
                    );
                }

            } catch (\Throwable $e) {

                if ($attempt === $this->maxAttempts) {
                    throw new RuntimeException(
                        "خطا در دریافت کانال ایتا: " .
                        $e->getMessage()
                    );
                }
            }

            if ($attempt < $this->maxAttempts) {
                usleep($this->retryDelay * 1000);
            }
        }

        throw new RuntimeException(
            "دریافت کانال ایتا ناموفق بود: {$url}"
        );
    }

    /**
     * استخراج پست‌ها از HTML
     */
    protected function parseHtml(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();

        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html
        );

        libxml_clear_errors();

        if (!$loaded) {
            throw new RuntimeException(
                'HTML کانال ایتا قابل پردازش نیست.'
            );
        }

        $xpath = new \DOMXPath($dom);

        $section = $xpath->query(
            '//section[contains(@class, "etme_channel_history")]'
        )->item(0);

        if (!$section) {
            return [];
        }

        $messages = $xpath->query(
            './/div[contains(@class, "etme_widget_message_wrap")]',
            $section
        );

        $result = [];

        foreach ($messages as $message) {

            $rawId = trim(
                $message->getAttribute('id')
            );

            if ($rawId === '') {
                continue;
            }

            /*
             * ID واقعی پیام را استخراج می‌کنیم.
             *
             * مثلاً اگر:
             *
             * message12345
             *
             * باشد:
             *
             * 12345
             */
            if (!preg_match('/(\d+)/', $rawId, $matches)) {
                continue;
            }

            $id = (int) $matches[1];

            if ($id <= 0) {
                continue;
            }

            /*
             * متن پیام
             */
            $textNode = $xpath->query(
                './/div[contains(@class, "etme_widget_message_text")]',
                $message
            )->item(0);

            if (!$textNode) {
                continue;
            }

            $text = trim(
                $textNode->textContent
            );

            /*
             * تاریخ
             */
            $timeNode = $xpath->query(
                './/time[contains(@class, "time")]',
                $message
            )->item(0);

            $datetime = null;

            if ($timeNode) {
                $datetime = trim(
                    $timeNode->getAttribute('datetime')
                );
            }

            $result[$id] = [
                'text' => $text,
                'date' => $datetime,
            ];
        }

        return $result;
    }

    /**
     * حذف پست‌های کاملاً تکراری
     */
    protected function removeDuplicateTitles(array $posts): array
    {
        $seen = [];

        foreach ($posts as $id => $post) {

            $title = trim(
                $post['text'] ?? ''
            );

            if ($title === '') {
                continue;
            }

            $normalizedTitle = $this->normalizeText(
                $title
            );

            /*
             * اگر عنوان قبلاً وجود نداشته
             * یا ID جدید بزرگ‌تر است، نگهش دار.
             */
            if (
                !isset($seen[$normalizedTitle]) ||
                $id > $seen[$normalizedTitle]
            ) {
                $seen[$normalizedTitle] = $id;
            }
        }

        $result = [];

        foreach ($seen as $id) {
            if (isset($posts[$id])) {
                $result[$id] = $posts[$id];
            }
        }

        return $result;
    }

    protected function cleanText(string $text): string
    {
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
            "/\n{3,}/u",
            "\n\n",
            $text
        );

        return trim($text);
    }

    protected function normalizeText(string $text): string
    {
        $text = $this->cleanText($text);

        $text = str_replace(
            ['ي', 'ى', 'ك'],
            ['ی', 'ی', 'ک'],
            $text
        );

        return mb_strtolower($text);
    }
}
