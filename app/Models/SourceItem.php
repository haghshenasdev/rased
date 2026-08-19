<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceItem extends Model
{
    protected $fillable = [
        'source_id',
        'external_id',
        'title',
        'url',
        'content',
        'matched_content',
        'matched_keyword',
        'published_at',
        'raw_data',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
