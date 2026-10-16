<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSession extends Model
{
    public const MODE_REAL = 'real';
    public const MODE_DEMO = 'demo';

    protected $fillable = [
        'token',
        'user_id',
        'game_id',
        'driver',
        'mode',
        'launch_url',
        'ip_address',
        'device',
        'expires_at',
        'ended_at',
    ];

    protected $hidden = ['ip_address'];

    protected $casts = [
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class);
    }

    public function isActive(): bool
    {
        return !$this->ended_at && $this->expires_at->isFuture();
    }
}
