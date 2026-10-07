<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'keywords', 'is_active'];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SourceItem::class);
    }
}
