<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GraphicSeeder extends Seeder
{
    public function run(): void
    {
        $graphics = [
            ['name' => 'Collegiate Text',      'slug' => 'collegiate-text',      'placement' => 'chest',       'description' => 'University-style bold serif typography'],
            ['name' => 'Brand Logo',           'slug' => 'brand-logo',           'placement' => 'chest/back',  'description' => 'Visible brand mark or wordmark'],
            ['name' => 'Abstract Print',       'slug' => 'abstract-print',       'placement' => 'all-over',    'description' => 'Non-representational pattern or graphic'],
            ['name' => 'Flame Graphic',        'slug' => 'flame-graphic',        'placement' => 'chest/sleeve','description' => 'Flame or fire motif'],
            ['name' => 'Skull',                'slug' => 'skull',                'placement' => 'chest/back',  'description' => 'Skull or skeleton imagery'],
            ['name' => 'Dragon',               'slug' => 'dragon',               'placement' => 'back/chest',  'description' => 'Dragon imagery, often eastern-inspired'],
            ['name' => 'Floral',               'slug' => 'floral',               'placement' => 'all-over',    'description' => 'Flower or botanical patterns'],
            ['name' => 'Tie-Dye',              'slug' => 'tie-dye',              'placement' => 'all-over',    'description' => 'Tie-dye wash pattern'],
            ['name' => 'Vintage Distressed',   'slug' => 'vintage-distressed',   'placement' => 'chest/back',  'description' => 'Faded, worn, deliberately aged graphic'],
            ['name' => 'Slogan Text',          'slug' => 'slogan-text',          'placement' => 'chest/back',  'description' => 'Phrase, slogan, or statement text'],
            ['name' => 'Number/Sport',         'slug' => 'number-sport',         'placement' => 'chest/back',  'description' => 'Athletic number, team name, or sport reference'],
            ['name' => 'City Name',            'slug' => 'city-name',            'placement' => 'chest',       'description' => 'City or location printed as graphic'],
            ['name' => 'Photography',          'slug' => 'photography',          'placement' => 'chest/back',  'description' => 'Photographic image printed on garment'],
            ['name' => 'Geometric',            'slug' => 'geometric',            'placement' => 'all-over',    'description' => 'Geometric shapes, lines, or patterns'],
            ['name' => 'Japanese / Kanji',     'slug' => 'japanese-kanji',       'placement' => 'chest/back',  'description' => 'Japanese characters or aesthetic references'],
            ['name' => 'Embroidered Patch',    'slug' => 'embroidered-patch',    'placement' => 'chest/sleeve','description' => 'Embroidery or patch application'],
            ['name' => 'No Graphic / Blank',   'slug' => 'no-graphic',           'placement' => null,          'description' => 'Solid color, no print or graphic'],
            ['name' => 'Tonal Print',          'slug' => 'tonal-print',          'placement' => 'all-over',    'description' => 'Single-color pattern that blends with base fabric'],
        ];

        foreach ($graphics as $g) {
            DB::table('graphics')->updateOrInsert(
                ['slug' => $g['slug']],
                array_merge($g, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
