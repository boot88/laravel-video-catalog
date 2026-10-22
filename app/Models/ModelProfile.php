<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ModelProfile extends Model
{
    protected $table = 'models';

    protected $fillable = [
        'name', 'slug', 'bio', 'photo', 'photo_credit', 'instagram',
        'city', 'height', 'consent_confirmed_at', 'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'height' => 'integer',
        'consent_confirmed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'model_video', 'model_id', 'video_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function getPhotoUrlAttribute(): string
    {
        return Media::url($this->photo, asset('media/models/model-01.webp'));
    }
}
