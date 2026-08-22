<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\Monitoring\MonitoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CheckSourceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 45;

    /**
     * جلوگیری از ایجاد Job تکراری
     * برای یک منبع
     */
    public $uniqueFor = 3600;

    public function __construct(
        public int $sourceId
    ) {
    }

    public function uniqueId(): string
    {
        return 'source-' . $this->sourceId;
    }

    public function handle(
        MonitoringService $monitoringService
    ): void {

        $source = Source::find(
            $this->sourceId
        );

        if (!$source || !$source->is_active) {
            return;
        }

        $stats = $monitoringService->monitor(
            $source
        );

        logger()->info(
            'بررسی منبع انجام شد',
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
