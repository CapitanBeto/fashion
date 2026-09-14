<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SilhouetteSeeder extends Seeder
{
    public function run(): void
    {
        $silhouettes = [
            ['name' => 'Oversized',     'slug' => 'oversized',      'description' => 'Deliberately large, relaxed fit with dropped shoulders'],
            ['name' => 'Boxy',          'slug' => 'boxy',           'description' => 'Square, structured silhouette with minimal taper'],
            ['name' => 'Cropped',       'slug' => 'cropped',        'description' => 'Shortened length, typically ending above the waist'],
            ['name' => 'Slim Fit',      'slug' => 'slim-fit',       'description' => 'Close to the body without being skin-tight'],
            ['name' => 'Regular Fit',   'slug' => 'regular-fit',    'description' => 'Standard cut with moderate ease'],
            ['name' => 'Relaxed',       'slug' => 'relaxed',        'description' => 'Slightly looser than regular, comfortable silhouette'],
            ['name' => 'Fitted',        'slug' => 'fitted',         'description' => 'Tailored close to body contour'],
            ['name' => 'Longline',      'slug' => 'longline',       'description' => 'Extended length, typically below the hip'],
            ['name' => 'Half-Zip',      'slug' => 'half-zip',       'description' => 'Zipper that runs halfway up the front'],
            ['name' => 'Full Zip',      'slug' => 'full-zip',       'description' => 'Zipper that runs the full length of the garment'],
            ['name' => 'Pullover',      'slug' => 'pullover',       'description' => 'No front opening, pulled on over the head'],
            ['name' => 'Raglan',        'slug' => 'raglan',         'description' => 'Sleeve extends to the collar with diagonal seam'],
            ['name' => 'Mock Neck',     'slug' => 'mock-neck',      'description' => 'Short, close-fitting collar that stands up'],
            ['name' => 'Turtleneck',    'slug' => 'turtleneck',     'description' => 'High-folded collar that covers the neck'],
            ['name' => 'Hoodie',        'slug' => 'hoodie',         'description' => 'Sweatshirt with attached hood and drawstrings'],
            ['name' => 'Coach Jacket',  'slug' => 'coach-jacket',   'description' => 'Lightweight jacket with snap buttons, typically nylon'],
            ['name' => 'Bomber',        'slug' => 'bomber',         'description' => 'Short jacket with ribbed cuffs and waistband'],
        ];

        foreach ($silhouettes as $s) {
            DB::table('silhouettes')->updateOrInsert(
                ['slug' => $s['slug']],
                array_merge($s, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
