<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            GameCategorySeeder::class,
            GameProviderSeeder::class,
            GameSeeder::class,
            GameHighlightSeeder::class,
            ContentSeeder::class,
        ]);
    }
}
