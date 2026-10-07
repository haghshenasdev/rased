<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\Monitoring\MonitoringErrorStore;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 45;
    public $uniqueFor = 3600;

    public function __construct(public int $sourceId) {}

    public function uniqueId(): string { return 'source-' . $this->sourceId; }

    public function handle(MonitoringService $monitoringService): void
    {
        $source = Source::find($this->sourceId);
        if (!$source || !$source->is_active) return;
        $monitoringService->monitor($source);
    }

    public function failed(Throwable $exception): void
    {
        $source = Source::find($this->sourceId);
        if ($source) {
            app(MonitoringErrorStore::class)->record($source, $exception, null, [
                'job_failed' => true,
                'source_id' => $source->id,
            ]);
        }
    }
}
