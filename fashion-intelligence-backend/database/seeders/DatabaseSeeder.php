<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            NicheSeeder::class,
            ColorSeeder::class,
            SilhouetteSeeder::class,
            GraphicSeeder::class,
            MaterialSeeder::class,
            ScoringParameterSeeder::class,
            SourceSeeder::class,
            PromptVersionSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
