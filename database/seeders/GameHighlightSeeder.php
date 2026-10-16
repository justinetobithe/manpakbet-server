<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameHighlightSeeder extends Seeder
{
    public function run(): void
    {
        Game::query()->where('badge', 'hot')->update(['badge' => null]);
        Game::query()->update(['is_featured' => false]);

        $hot = GameCatalog::HOT_GAMES;
        $count = count($hot);

        foreach ($hot as $index => $slug) {
            Game::query()->where('slug', $slug)->update([
                'badge' => 'hot',
                'is_featured' => true,
                'sort_order' => $index + 1,
                'plays_count' => $slug === GameCatalog::SPOTLIGHT ? 99800 : 60000 + ($count - $index) * 3500,
            ]);
        }

        $tags = ['top', 'new', 'jackpot'];

        Game::query()
            ->whereNotIn('slug', $hot)
            ->orderByDesc('plays_count')
            ->limit(60)
            ->get(['id', 'slug'])
            ->each(function (Game $game, int $index) use ($tags) {
                $game->forceFill([
                    'is_featured' => $index < 12,
                    'badge' => $index % 3 === 0 ? $tags[$index % count($tags)] : null,
                ])->save();
            });
    }
}
