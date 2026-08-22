<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaleSubscriber extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'token',
        'chat_id',
        'is_active',
        'connected_at',
        'last_sent_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function isConnected(): bool
    {
        return !empty($this->chat_id);
    }

    protected static function booted(): void
    {
        static::creating(function (BaleSubscriber $subscriber) {

            if (!$subscriber->token) {
                $subscriber->token =
                    'RSD-' .
                    strtoupper(
                        \Illuminate\Support\Str::random(12)
                    );
            }
        });
    }
}
