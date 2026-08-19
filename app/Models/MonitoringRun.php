<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'started_at',
        'finished_at',
        'sources_count',
        'items_read',
        'items_matched',
        'items_saved',
        'status',
        'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(MonitoringLog::class);
    }
}
