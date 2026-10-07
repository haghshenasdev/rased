<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringLog;
use App\Models\MonitoringRun;
use App\Models\Source;

class MonitoringErrorStore
{
    public function record(Source $source, \Throwable $e, ?MonitoringRun $run = null, array $context = []): void
    {
        MonitoringLog::create([
            'monitoring_run_id' => $run?->id ?? $this->ensureRun($source)->id,
            'source_id' => $source->id,
            'items_read' => 0,
            'items_matched' => 0,
            'items_saved' => 0,
            'status' => 'failed',
            'error_type' => get_class($e),
            'message' => mb_substr($e->getMessage(), 0, 5000),
            'context' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);

        $ids = MonitoringLog::query()
            ->where('source_id', $source->id)
            ->where('status', 'failed')
            ->latest('id')
            ->pluck('id');

        $deleteIds = $ids->slice(3);
        if ($deleteIds->isNotEmpty()) {
            MonitoringLog::whereIn('id', $deleteIds->all())->delete();
        }
    }

    private function ensureRun(Source $source): MonitoringRun
    {
        return MonitoringRun::create([
            'started_at' => now(),
            'finished_at' => now(),
            'sources_count' => 1,
            'status' => 'failed',
            'error' => 'ثبت خطای منبع',
        ]);
    }
}
