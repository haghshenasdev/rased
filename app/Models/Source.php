<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'name','type','url','identifier','settings','last_item_id','last_item_url',
        'last_read_at','is_active','parent_id','profile_image_path','profile_image_url',
        'default_category_id','auto_categorize','ignore_link_keyword',
    ];

    protected $casts = [
        'settings' => 'array',
        'last_read_at' => 'datetime',
        'is_active' => 'boolean',
        'auto_categorize' => 'boolean',
        'ignore_link_keyword' => 'boolean',
    ];

    public function items(): HasMany { return $this->hasMany(SourceItem::class); }
    public function monitoringLogs(): HasMany { return $this->hasMany(MonitoringLog::class); }
    public function parent(): BelongsTo { return $this->belongsTo(Source::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Source::class, 'parent_id'); }
    public function defaultCategory(): BelongsTo { return $this->belongsTo(Category::class, 'default_category_id'); }

    public function feedUrls(): array
    {
        $urls = $this->settings['feed_urls'] ?? [];
        if (!is_array($urls)) $urls = [];
        $urls = array_values(array_filter(array_map('trim', $urls)));
        if ($this->url) array_unshift($urls, trim($this->url));
        return array_values(array_unique($urls));
    }

    public function profileImage(): ?string
    {
        if ($this->profile_image_url) return $this->profile_image_url;
        if ($this->profile_image_path) return asset('storage/' . ltrim($this->profile_image_path, '/'));
        return null;
    }
}
