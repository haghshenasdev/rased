<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\Monitoring\MonitoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CheckSourceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $sourceId
    ) {
    }

    public function handle(
        MonitoringService $monitoringService
    ): void {

        $source = Source::find($this->sourceId);

        if (!$source) {
            return;
        }

        if (!$source->is_active) {
            return;
        }

        $stats = $monitoringService->monitor($source);

        logger()->info(
            'بررسی منبع با موفقیت انجام شد',
            $stats
        );
    }

    public function failed(
        Throwable $exception
    ): void {

        logger()->error(
            'بررسی منبع شکست خورد',
            [
                'source_id' => $this->sourceId,
                'error' => $exception->getMessage(),
            ]
        );
    }
}
