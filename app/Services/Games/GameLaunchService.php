<?php

namespace App\Services\Games;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GameLaunchService
{
    public function __construct(private readonly GameProviderManager $providers)
    {
    }

    public function launch(User $user, Game $game, string $mode, Request $request): GameSession
    {
        if ($mode === GameSession::MODE_DEMO && !$game->has_demo) {
            throw new GameProviderException('Demo mode is not available for this game', 'DEMO_UNAVAILABLE');
        }

        $driver = $this->providers->driver();

        GameSession::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $session = GameSession::query()->create([
            'token' => (string) Str::uuid(),
            'user_id' => $user->id,
            'game_id' => $game->id,
            'driver' => $driver->key(),
            'mode' => $mode,
            'ip_address' => $request->ip(),
            'device' => preg_match('/Mobile|Android|iPhone|iPad/i', (string) $request->userAgent()) ? 'mobile' : 'desktop',
            'expires_at' => now()->addMinutes((int) config('games.session_ttl_minutes', 240)),
        ]);

        $session->launch_url = $driver->isConfigured() ? $driver->launch($session) : null;
        $session->save();

        $game->increment('plays_count');

        return $session->load('game.providerRef:id,name,slug,logo');
    }

    public function end(User $user, string $token): void
    {
        GameSession::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }
}
