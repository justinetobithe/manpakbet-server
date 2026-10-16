<?php

namespace App\Services\Games\Drivers;

use App\Models\GameSession;
use App\Services\Games\Contracts\GameProviderDriver;
use Illuminate\Http\Request;

class DemoDriver implements GameProviderDriver
{
    public function key(): string
    {
        return 'demo';
    }

    public function name(): string
    {
        return (string) config('games.drivers.demo.name', 'Demo');
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function launch(GameSession $session): ?string
    {
        return null;
    }

    public function verifyCallback(Request $request): bool
    {
        return false;
    }
}
