<?php

namespace App\Services\Games;

use App\Services\Games\Contracts\GameProviderDriver;
use App\Services\Games\Drivers\AggregatorDriver;
use App\Services\Games\Drivers\DemoDriver;

class GameProviderManager
{
    private const DRIVERS = [
        'demo' => DemoDriver::class,
        'aggregator' => AggregatorDriver::class,
    ];

    private array $resolved = [];

    public function driver(?string $key = null): GameProviderDriver
    {
        $key ??= (string) config('games.driver', 'demo');

        if (!isset(self::DRIVERS[$key])) {
            throw new GameProviderException("Unknown game provider driver [{$key}]", 'UNKNOWN_DRIVER', 404);
        }

        return $this->resolved[$key] ??= app(self::DRIVERS[$key]);
    }

    public function has(string $key): bool
    {
        return isset(self::DRIVERS[$key]);
    }
}
