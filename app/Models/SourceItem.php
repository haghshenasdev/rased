<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceItem extends Model
{
    protected $fillable = [
        'source_id','external_id','title','url','featured_image_url','content',
        'matched_content','matched_keyword','published_at','raw_data','category_id',
        'duplicate_of_id','similarity_percent','is_repost',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'raw_data' => 'array',
        'is_repost' => 'boolean',
        'similarity_percent' => 'float',
    ];

    public function source(): BelongsTo { return $this->belongsTo(Source::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function original(): BelongsTo { return $this->belongsTo(SourceItem::class, 'duplicate_of_id'); }
    public function reposts(): HasMany { return $this->hasMany(SourceItem::class, 'duplicate_of_id'); }
}
