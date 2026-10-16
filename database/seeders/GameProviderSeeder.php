<?php

namespace Database\Seeders;

use App\Models\GameProvider;
use Illuminate\Database\Seeder;

class GameProviderSeeder extends Seeder
{
    private const FEATURED = ['pg-soft', 'jili', 'pragmatic-play', 'evolution'];

    public function run(): void
    {
        $providers = collect(GameCatalog::load())
            ->groupBy('provider_slug')
            ->map(fn ($games) => ['name' => $games->first()['provider'], 'count' => $games->count()])
            ->sortByDesc('count');

        $ordered = collect(self::FEATURED)
            ->filter(fn ($slug) => $providers->has($slug))
            ->merge($providers->keys()->diff(self::FEATURED))
            ->values();

        foreach ($ordered as $index => $slug) {
            GameProvider::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $providers[$slug]['name'], 'sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }
}
