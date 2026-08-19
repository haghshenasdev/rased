<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class RssReader implements SourceReaderInterface
{
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

    protected function fetch(string $url): string
    {
        $response = Http::timeout(30)
            ->withoutVerifying()
            ->retry(3, 1500)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Connection' => 'keep-alive',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException(
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

        $rss = simplexml_load_string($xml);

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
     * @param iterable<SimpleXMLElement> $items
     * @return SourceItemData[]
     */
    protected function parseRssItems(iterable $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $title = trim((string) $item->title);

            $link = trim((string) $item->link);

            $description = trim((string) $item->description);

            $guid = trim((string) $item->guid);

            $pubDate = trim((string) $item->pubDate);

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
                content: $description ?: null,
                publishedAt: $publishedAt,
                rawData: [
                    'guid' => $guid,
                    'pubDate' => $pubDate,
                    'description' => $description,
                ],
            );
        }

        return $result;
    }

    /**
     * @param iterable<SimpleXMLElement> $entries
     * @return SourceItemData[]
     */
    protected function parseAtomEntries(iterable $entries): array
    {
        $result = [];

        foreach ($entries as $entry) {
            $title = trim((string) $entry->title);

            $id = trim((string) $entry->id);

            $content = trim((string) $entry->content);

            if (!$content) {
                $content = trim((string) $entry->summary);
            }

            $url = null;

            if (isset($entry->link)) {
                foreach ($entry->link as $link) {
                    $attributes = $link->attributes();

                    if (
                        isset($attributes['rel']) &&
                        (string) $attributes['rel'] === 'alternate'
                    ) {
                        $url = (string) $attributes['href'];
                        break;
                    }

                    if (!$url && isset($attributes['href'])) {
                        $url = (string) $attributes['href'];
                    }
                }
            }

            $published = trim((string) $entry->published);

            if (!$published) {
                $published = trim((string) $entry->updated);
            }

            $publishedAt = null;

            if ($published) {
                try {
                    $publishedAt = Carbon::parse($published);
                } catch (\Throwable) {
                    $publishedAt = null;
                }
            }

            $externalId = $id ?: $url;

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
                    'updated' => (string) $entry->updated,
                ],
            );
        }

        return $result;
    }
}
