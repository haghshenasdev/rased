<?php

namespace App\Services\Monitoring;

use Carbon\Carbon;

class SourceItemData
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $title,
        public readonly ?string $url = null,
        public readonly ?string $content = null,
        public readonly ?Carbon $publishedAt = null,
        public readonly ?string $featuredImageUrl = null,
        public readonly array $rawData = [],
    ) {}

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'title' => $this->title,
            'url' => $this->url,
            'content' => $this->content,
            'published_at' => $this->publishedAt,
            'featured_image_url' => $this->featuredImageUrl,
            'raw_data' => $this->rawData,
        ];
    }
}
