<?php

namespace Database\Seeders;

use App\Models\GameProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GameProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['name' => 'JILI', 'slug' => 'jili', 'logo' => '/providers/jili.png', 'sort_order' => 1],
            ['name' => 'Pragmatic Play', 'slug' => 'pragmatic-play', 'logo' => '/providers/pragmatic.png', 'sort_order' => 2],
            ['name' => 'PG Soft', 'slug' => 'pgsoft', 'logo' => '/providers/pgsoft.png', 'sort_order' => 3],
        ];

        foreach ($providers as $p) {
            GameProvider::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'logo' => $p['logo'],
                    'sort_order' => $p['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
