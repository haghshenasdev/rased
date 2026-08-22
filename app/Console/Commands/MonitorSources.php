<?php

namespace App\Console\Commands;

use App\Jobs\CheckSourceJob;
use App\Models\Source;
use Illuminate\Console\Command;

class MonitorSources extends Command
{
    protected $signature = 'monitoring:run';

    protected $description = 'ارسال منابعی که بیش از یک ساعت از آخرین بررسی آنها گذشته';

    public function handle(): int
    {
        $limitTime = now()->subHour();

        $sources = Source::query()
            ->where('is_active', true)
            ->where(function ($query) use ($limitTime) {

                $query
                    ->whereNull('last_read_at')
                    ->orWhere(
                        'last_read_at',
                        '<=',
                        $limitTime
                    );

            })
            ->orderBy('last_read_at')
            ->limit(50)
            ->get();

        foreach ($sources as $source) {

            CheckSourceJob::dispatch(
                $source->id
            );

            $this->info(
                "Job ارسال شد: {$source->name}"
            );
        }

        $this->info(
            "{$sources->count()} منبع برای بررسی ارسال شد."
        );

        return self::SUCCESS;
    }
}
