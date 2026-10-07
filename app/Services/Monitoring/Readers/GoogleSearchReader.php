<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use App\Services\Monitoring\SourceItemData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSearchReader implements SourceReaderInterface
{
    public function read(Source $source): array
    {
        $key = config('services.google_search.key');
        $cx = config('services.google_search.cx');
        if (!$key || !$cx) {
            throw new RuntimeException('GOOGLE_SEARCH_API_KEY و GOOGLE_SEARCH_CX در .env تنظیم نشده‌اند.');
        }

        $query = trim((string)($source->settings['query'] ?? ''));
        if ($query === '') {
            throw new RuntimeException('کلمه کلیدی جست‌وجوی Google برای این منبع مشخص نشده است.');
        }

        $count = min(10, max(1, (int)($source->settings['count'] ?? 10)));
        $response = Http::connectTimeout(10)->timeout(20)->get(
            'https://www.googleapis.com/customsearch/v1',
            [
                'key' => $key, 'cx' => $cx, 'q' => $query,
                'num' => $count, 'hl' => 'fa', 'safe' => 'off',
            ]
        );

        if (!$response->successful()) {
            throw new RuntimeException('Google Custom Search: HTTP ' . $response->status() . ' - ' . $response->body());
        }

        $json = $response->json();
        $items = $json['items'] ?? [];
        $result = [];

        foreach ($items as $item) {
            $link = trim((string)($item['link'] ?? ''));
            $title = trim((string)($item['title'] ?? ''));
            if ($link === '' || $title === '') continue;

            $image = $item['pagemap']['cse_image'][0]['src']
                ?? $item['pagemap']['metatags'][0]['og:image']
                ?? null;

            $published = $item['pagemap']['metatags'][0]['article:published_time'] ?? null;
            $publishedAt = null;
            if ($published) {
                try { $publishedAt = Carbon::parse($published)->setTimezone('Asia/Tehran'); } catch (\Throwable) {}
            }

            $result[] = new SourceItemData(
                externalId: sha1($link),
                title: $title,
                url: $link,
                content: trim((string)($item['snippet'] ?? '')),
                publishedAt: $publishedAt,
                featuredImageUrl: $image,
                rawData: ['google_query' => $query, 'displayLink' => $item['displayLink'] ?? null],
            );
        }

        return $result;
    }
}
