<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NicheSeeder extends Seeder
{
    public function run(): void
    {
        $niches = [
            [
                'name'              => 'Streetwear',
                'slug'              => 'streetwear',
                'priority'          => 100,
                'keywords'          => json_encode(['streetwear', 'street style', 'urban fashion', 'hype', 'drop', 'collab', 'hoodie', 'cargo', 'oversized', 'graphic tee', 'sneaker']),
                'excluded_keywords' => json_encode(['formal', 'suit', 'evening wear']),
            ],
            [
                'name'              => 'Workwear',
                'slug'              => 'workwear',
                'priority'          => 80,
                'keywords'          => json_encode(['workwear', 'utilitarian', 'gorpcore', 'outdoor', 'functional', 'technical fabric', 'fleece', 'cargo pants']),
                'excluded_keywords' => json_encode(['office', 'corporate']),
            ],
            [
                'name'              => 'Y2K / Nostalgic',
                'slug'              => 'y2k',
                'priority'          => 70,
                'keywords'          => json_encode(['y2k', '2000s fashion', 'early 2000s', 'nostalgia', 'low rise', 'butterfly', 'frosted tips', 'velour', 'baby tee']),
                'excluded_keywords' => json_encode([]),
            ],
            [
                'name'              => 'Minimalism',
                'slug'              => 'minimalism',
                'priority'          => 60,
                'keywords'          => json_encode(['minimalist', 'clean aesthetic', 'quiet luxury', 'neutral', 'tonal', 'basics', 'capsule wardrobe', 'uniqlo', 'simple']),
                'excluded_keywords' => json_encode(['loud', 'maximalist', 'bold print']),
            ],
            [
                'name'              => 'Athleisure',
                'slug'              => 'athleisure',
                'priority'          => 60,
                'keywords'          => json_encode(['athleisure', 'sportswear', 'activewear', 'gym wear', 'leggings', 'sports bra', 'running', 'yoga', 'performance']),
                'excluded_keywords' => json_encode([]),
            ],
            [
                'name'              => 'Skate',
                'slug'              => 'skate',
                'priority'          => 50,
                'keywords'          => json_encode(['skate', 'skateboard', 'sk8', 'vans', 'supreme', 'thrasher', 'boxy', 'baggy', 'beanie', 'deck']),
                'excluded_keywords' => json_encode([]),
            ],
            [
                'name'              => 'Luxury Streetwear',
                'slug'              => 'luxury-streetwear',
                'priority'          => 50,
                'keywords'          => json_encode(['luxury streetwear', 'designer', 'high end', 'balenciaga', 'off-white', 'stone island', 'palace', 'premium']),
                'excluded_keywords' => json_encode(['budget', 'affordable', 'cheap']),
            ],
        ];

        foreach ($niches as $niche) {
            DB::table('niches')->updateOrInsert(
                ['slug' => $niche['slug']],
                array_merge($niche, [
                    'active'     => true,
                    'metadata'   => '{}',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
