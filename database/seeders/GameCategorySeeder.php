<?php

namespace Database\Seeders;

use App\Models\GameCategory;
use Illuminate\Database\Seeder;

class GameCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Hot Games', 'slug' => 'popular', 'icon' => 'flame'],
            ['name' => 'Slot Games', 'slug' => 'slot', 'icon' => 'gamepad'],
            ['name' => 'Live Casino', 'slug' => 'live', 'icon' => 'video'],
            ['name' => 'Fishing', 'slug' => 'fishing', 'icon' => 'fish'],
            ['name' => 'New Games', 'slug' => 'new', 'icon' => 'sparkles'],
            ['name' => 'Poker', 'slug' => 'poker', 'icon' => 'spade'],
            ['name' => 'Sports', 'slug' => 'sports', 'icon' => 'trophy'],
        ];

        foreach ($categories as $index => $category) {
            GameCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }
}
