<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\Monitoring\Readers\SourceReaderFactory;
use Illuminate\Console\Command;
use Throwable;

class TestSourceReader extends Command
{
    protected $signature = 'monitoring:test-source {source}';

    protected $description = 'تست خواندن یک منبع';

    public function handle(SourceReaderFactory $factory): int
    {
        $source = Source::find($this->argument('source'));

        if (!$source) {
            $this->error('منبع پیدا نشد.');

            return self::FAILURE;
        }

        if (!$source->is_active) {
            $this->warn('این منبع غیرفعال است.');

            return self::FAILURE;
        }

        $this->info("در حال خواندن: {$source->name}");

        try {
            $reader = $factory->make($source);

            $items = $reader->read($source);

            $this->info(
                'تعداد مطالب دریافت شده: ' . count($items)
            );

            foreach ($items as $index => $item) {
                $this->newLine();

                $this->line(
                    ($index + 1) . '. ' . $item->title
                );

                $this->line(
                    'ID: ' . $item->externalId
                );

                $this->line(
                    'URL: ' . ($item->url ?? '-')
                );

                $this->line(
                    'Date: ' . (
                        $item->publishedAt?->format('Y-m-d H:i:s')
                        ?? '-'
                    )
                );
            }

            return self::SUCCESS;

        } catch (Throwable $e) {

            $this->error(
                'خطا: ' . $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
