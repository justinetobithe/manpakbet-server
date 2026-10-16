<?php

namespace App\Services\Games\Drivers;

use App\Models\GameSession;
use App\Services\Games\Contracts\GameProviderDriver;
use App\Services\Games\GameProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AggregatorDriver implements GameProviderDriver
{
    private array $config;

    public function __construct()
    {
        $this->config = (array) config('games.drivers.aggregator', []);
    }

    public function key(): string
    {
        return 'aggregator';
    }

    public function name(): string
    {
        return (string) ($this->config['name'] ?? 'Game Aggregator');
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['base_url']) && !empty($this->config['operator_id']) && !empty($this->config['secret']);
    }

    public function launch(GameSession $session): ?string
    {
        if (!$this->isConfigured()) {
            throw new GameProviderException('Game provider is not configured');
        }

        $session->loadMissing(['game', 'user']);

        $payload = [
            'operator_id' => $this->config['operator_id'],
            'token' => $session->token,
            'player_id' => (string) $session->user_id,
            'nickname' => $session->user->name,
            'game_code' => $session->game->provider_game_code ?: $session->game->slug,
            'provider' => $session->game->provider,
            'mode' => $session->mode,
            'currency' => config('games.currency'),
            'language' => config('games.language'),
            'device' => $session->device ?? 'desktop',
            'lobby_url' => config('games.lobby_url'),
            'timestamp' => now()->timestamp,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 15))
                ->withHeaders([
                    'Accept' => 'application/json',
                    $this->config['signature_header'] ?? 'X-Signature' => $this->sign($body),
                ])
                ->withBody($body, 'application/json')
                ->post(rtrim($this->config['base_url'], '/') . '/' . ltrim($this->config['launch_path'] ?? '/game/launch', '/'));
        } catch (\Throwable $e) {
            Log::warning('Game launch request failed', ['error' => $e->getMessage(), 'session' => $session->token]);
            throw new GameProviderException('Unable to reach the game provider');
        }

        if (!$response->successful()) {
            Log::warning('Game launch rejected', ['status' => $response->status(), 'body' => $response->body(), 'session' => $session->token]);
            throw new GameProviderException('The game provider rejected the launch request');
        }

        $url = data_get($response->json(), $this->config['launch_url_field'] ?? 'url');

        if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
            throw new GameProviderException('The game provider did not return a launch URL');
        }

        return $url;
    }

    public function verifyCallback(Request $request): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $allowed = $this->config['allowed_ips'] ?? [];
        if ($allowed && !in_array($request->ip(), $allowed, true)) {
            return false;
        }

        $signature = (string) $request->header($this->config['signature_header'] ?? 'X-Signature', '');

        return $signature !== '' && hash_equals($this->sign($request->getContent()), $signature);
    }

    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) $this->config['secret']);
    }
}
