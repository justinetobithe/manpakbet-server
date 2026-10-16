<?php

namespace Database\Seeders;

use App\Models\GameCategory;
use Illuminate\Database\Seeder;

class GameCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Popular Games', 'slug' => 'popular', 'icon' => 'flame', 'sort_order' => 1],
            ['name' => 'Live Games', 'slug' => 'live', 'icon' => 'video', 'sort_order' => 2],
            ['name' => 'Slot Games', 'slug' => 'slot', 'icon' => 'gamepad', 'sort_order' => 3],
            ['name' => 'New Games', 'slug' => 'new', 'icon' => 'sparkles', 'sort_order' => 4],
            ['name' => 'Fishing', 'slug' => 'fishing', 'icon' => 'fish', 'sort_order' => 5],
            ['name' => 'Sports', 'slug' => 'sports', 'icon' => 'trophy', 'sort_order' => 6],
            ['name' => 'Poker', 'slug' => 'poker', 'icon' => 'spade', 'sort_order' => 7],
        ];

        foreach ($categories as $c) {
            GameCategory::updateOrCreate(
                ['slug' => $c['slug']],
                [
                    'name' => $c['name'],
                    'icon' => $c['icon'],
                    'sort_order' => $c['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
