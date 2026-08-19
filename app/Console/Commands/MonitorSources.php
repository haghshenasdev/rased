<?php

namespace App\Console\Commands;

use App\Jobs\CheckSourceJob;
use App\Models\Source;
use Illuminate\Console\Command;

class MonitorSources extends Command
{
    protected $signature = 'monitoring:run';

    protected $description =
        'بررسی تمام منابع فعال';

    public function handle(): int
    {
        $sources = Source::query()
            ->where('is_active', true)
            ->get();

        $this->info(
            "شروع بررسی {$sources->count()} منبع..."
        );

        foreach ($sources as $source) {

            $this->line(
                "ارسال {$source->name} به صف..."
            );

            CheckSourceJob::dispatch(
                $source->id
            );
        }

        $this->info(
            'تمام منابع برای بررسی ارسال شدند.'
        );

        return self::SUCCESS;
    }
}
