<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'url',
        'identifier',
        'settings',
        'last_item_id',
        'last_item_url',
        'last_read_at',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'last_read_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SourceItem::class);
    }

    public function monitoringLogs(): HasMany
    {
        return $this->hasMany(MonitoringLog::class);
    }
}
