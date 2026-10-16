<?php

namespace App\Services\Games\Contracts;

use App\Models\GameSession;
use Illuminate\Http\Request;

interface GameProviderDriver
{
    public function key(): string;

    public function name(): string;

    public function isConfigured(): bool;

    public function launch(GameSession $session): ?string;

    public function verifyCallback(Request $request): bool;
}
