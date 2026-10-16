<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameHighlightSeeder extends Seeder
{
    public function run(): void
    {
        $badges = ['hot', 'new', 'top', 'jackpot'];

        Game::query()->orderBy('id')->each(function (Game $game) use ($badges) {
            $seed = crc32($game->slug);

            $game->forceFill([
                'plays_count' => $game->plays_count ?: ($seed % 50000) + 250,
                'is_featured' => $seed % 7 === 0,
                'badge' => $game->badge ?? ($seed % 5 === 0 ? $badges[$seed % count($badges)] : null),
            ])->save();
        });
    }
}
