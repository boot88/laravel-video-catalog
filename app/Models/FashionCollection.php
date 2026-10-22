<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FashionCollection extends Model
{
    protected $table = 'collections';

    protected $fillable = [
        'brand_id', 'title', 'slug', 'season', 'city', 'event_date',
        'description', 'cover', 'photo_credit', 'is_visible',
    ];

    protected $casts = [
        'event_date' => 'date',
        'is_visible' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'collection_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function getCoverUrlAttribute(): string
    {
        return Media::url($this->cover, asset('media/models/model-02.webp'));
    }
}
