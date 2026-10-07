<?php

namespace App\Services;

use App\Models\MonitoringLog;
use App\Models\MonitoringRun;
use App\Models\Source;
use App\Models\SourceItem;
use Throwable;

class BaleSendLogStore
{
    public function record(
        ?SourceItem $item,
        string $status,
        string $message,
        array $context = []
    ): void {
        $source = $item?->source;

        if (!$source) {
            return;
        }

        $run = MonitoringRun::create([
            'started_at' => now(),
            'finished_at' => now(),
            'sources_count' => 1,
            'status' => $status === 'success' ? 'completed' : 'failed',
            'error' => $status === 'success' ? null : mb_substr($message, 0, 5000),
        ]);

        MonitoringLog::create([
            'monitoring_run_id' => $run->id,
            'source_id' => $source->id,
            'items_read' => 0,
            'items_matched' => 0,
            'items_saved' => 0,
            'status' => $status === 'success' ? 'success' : 'failed',
            'error_type' => $status === 'success' ? 'BALE_SEND_SUCCESS' : 'BALE_SEND_FAILED',
            'message' => mb_substr($message, 0, 5000),
            'context' => json_encode(array_merge([
                'operation' => 'bale_send',
                'source_item_id' => $item->id,
                'source_name' => $source->name,
                'title' => $item->title,
            ], $context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if ($status !== 'success') {
            $ids = MonitoringLog::query()
                ->where('source_id', $source->id)
                ->where('status', 'failed')
                ->where('error_type', 'BALE_SEND_FAILED')
                ->latest('id')
                ->pluck('id');

            $deleteIds = $ids->slice(3);
            if ($deleteIds->isNotEmpty()) {
                MonitoringLog::whereIn('id', $deleteIds->all())->delete();
            }
        }
    }

    public function exception(?SourceItem $item, Throwable $e, array $context = []): void
    {
        $this->record(
            $item,
            'failed',
            $e->getMessage(),
            array_merge($context, [
                'exception' => get_class($e),
            ])
        );
    }
}
