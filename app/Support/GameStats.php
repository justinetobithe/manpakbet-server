<?php

namespace App\Support;

use App\Models\GameRound;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\Builder;

class GameStats
{
    public static function rounds(callable $scope): array
    {
        $base = fn () => tap(GameRound::query()->where('status', GameRound::STATUS_COMPLETED), fn (Builder $query) => $scope($query));

        $bets = (float) $base()->where('type', GameRound::TYPE_BET)->sum('amount');
        $wins = (float) $base()->where('type', GameRound::TYPE_WIN)->sum('amount');

        return [
            'rounds' => (int) tap(GameRound::query(), fn (Builder $query) => $scope($query))->distinct()->count('round_id'),
            'total_bets' => $bets,
            'total_wins' => $wins,
            'ggr' => round($bets - $wins, 2),
            'rtp' => $bets > 0 ? round($wins / $bets * 100, 2) : null,
        ];
    }

    public static function sessions(callable $scope): array
    {
        $query = fn () => tap(GameSession::query(), fn (Builder $q) => $scope($q));

        return [
            'sessions' => $query()->count(),
            'real_sessions' => $query()->where('mode', GameSession::MODE_REAL)->count(),
            'demo_sessions' => $query()->where('mode', GameSession::MODE_DEMO)->count(),
            'last_played_at' => $query()->max('created_at'),
        ];
    }
}
