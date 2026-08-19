<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitoring_run_id',
        'source_id',
        'items_read',
        'items_matched',
        'items_saved',
        'status',
        'message',
    ];

    public function monitoringRun(): BelongsTo
    {
        return $this->belongsTo(MonitoringRun::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
