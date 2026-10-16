<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\File;
use RuntimeException;

class GameCatalog
{
    public const PATH = 'database/data/casinoplus-games.json';

    public const HOT_GAMES = [
        'pinata-wins',
        'super-ace',
        'fortune-gems',
        'fortune-gems-2',
        'fortune-gems-3',
        'wild-bounty-showdown',
        'wild-ape3258',
        'golden-empire',
    ];

    public const SPOTLIGHT = 'pinata-wins';

    private static ?array $cache = null;

    public static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = base_path(self::PATH);

        if (!File::exists($path)) {
            throw new RuntimeException('Game catalog missing. Run: php artisan games:crawl-casinoplus');
        }

        return self::$cache = json_decode(File::get($path), true) ?? [];
    }
}
