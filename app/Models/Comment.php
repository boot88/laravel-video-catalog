<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $fillable = [
        'video_id', 'username', 'content', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime:d.m.Y H:i',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Video::class);
    }
}
