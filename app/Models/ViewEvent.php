<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViewEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['video_id', 'session_id', 'ip_hash'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
