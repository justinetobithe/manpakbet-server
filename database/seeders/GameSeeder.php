<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameSeeder extends Seeder
{
    private const NEW_GAMES_PER_PROVIDER = 3;

    public function run(): void
    {
        $catalog = GameCatalog::load();
        $categoryIds = GameCategory::query()->pluck('id', 'slug');
        $providerIds = GameProvider::query()->pluck('id', 'slug');
        $hot = array_flip(GameCatalog::HOT_GAMES);
        $total = count($catalog);
        $sort = [];
        $newPerProvider = [];

        DB::transaction(function () use ($catalog, $categoryIds, $providerIds, $hot, $total, &$sort, &$newPerProvider) {
            foreach ($catalog as $game) {
                $category = isset($hot[$game['slug']]) ? 'popular' : $game['category'];

                if ($category === 'slot' && ($newPerProvider[$game['provider_slug']] ?? 0) < self::NEW_GAMES_PER_PROVIDER && $game['rank'] > 40) {
                    $newPerProvider[$game['provider_slug']] = ($newPerProvider[$game['provider_slug']] ?? 0) + 1;
                    $category = 'new';
                }

                $sort[$category] = ($sort[$category] ?? 0) + 1;

                Game::query()->updateOrCreate(
                    ['slug' => $game['slug']],
                    [
                        'name' => $game['name'],
                        'game_category_id' => $categoryIds[$category] ?? $categoryIds['slot'],
                        'game_provider_id' => $providerIds[$game['provider_slug']] ?? null,
                        'provider' => $game['provider'],
                        'thumbnail' => $game['image'],
                        'banner' => $game['image'],
                        'launch_url' => null,
                        'has_demo' => !in_array($game['category'], ['live', 'sports'], true),
                        'sort_order' => isset($hot[$game['slug']]) ? $hot[$game['slug']] + 1 : $sort[$category],
                        'plays_count' => max(120, (int) round(($total - $game['rank'] + 1) / $total * 48000) + crc32($game['slug']) % 900),
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
