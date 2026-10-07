<?php

namespace App\Services\Monitoring\Readers;

use App\Models\Source;
use InvalidArgumentException;

class SourceReaderFactory
{
    public function make(Source $source): SourceReaderInterface
    {
        return match ($source->type) {
            'rss' => app(RssReader::class),
            'eitaa' => app(EitaaReader::class),
            'browser', 'html', 'javascript' => app(BrowserReader::class),
            'farsnews' => app(FarsNewsReader::class),
            'google' => app(GoogleSearchReader::class),
            default => throw new InvalidArgumentException("برای نوع منبع [{$source->type}] Reader وجود ندارد."),
        };
    }
}
