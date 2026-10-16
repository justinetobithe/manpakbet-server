<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRound extends Model
{
    public const TYPE_BET = 'bet';
    public const TYPE_WIN = 'win';
    public const TYPE_REFUND = 'refund';

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'game_session_id',
        'user_id',
        'game_id',
        'driver',
        'external_id',
        'round_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'status',
        'reference_external_id',
        'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'payload' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
