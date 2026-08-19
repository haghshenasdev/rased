<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlacklistKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'word',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
