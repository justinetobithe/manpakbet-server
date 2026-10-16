<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameProvider extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
