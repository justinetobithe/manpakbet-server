<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    protected $fillable = [
        'game_category_id',
        'game_provider_id',
        'name',
        'slug',
        'provider',
        'thumbnail',
        'banner',
        'launch_url',
        'badge',
        'is_featured',
        'plays_count',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'plays_count' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'game_category_id');
    }

    public function providerRef(): BelongsTo
    {
        return $this->belongsTo(GameProvider::class, 'game_provider_id');
    }
}
