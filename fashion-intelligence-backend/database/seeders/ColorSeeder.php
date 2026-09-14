<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            // Neutrals
            ['name' => 'Black',         'hex' => '#000000', 'color_family' => 'neutral',  'temperature' => 'neutral', 'rgb' => '{"r":0,"g":0,"b":0}'],
            ['name' => 'White',         'hex' => '#FFFFFF', 'color_family' => 'neutral',  'temperature' => 'neutral', 'rgb' => '{"r":255,"g":255,"b":255}'],
            ['name' => 'Off White',     'hex' => '#FAF9F6', 'color_family' => 'neutral',  'temperature' => 'warm',    'rgb' => '{"r":250,"g":249,"b":246}'],
            ['name' => 'Ecru',          'hex' => '#C2B280', 'color_family' => 'neutral',  'temperature' => 'warm',    'rgb' => '{"r":194,"g":178,"b":128}'],
            ['name' => 'Charcoal',      'hex' => '#36454F', 'color_family' => 'neutral',  'temperature' => 'cool',    'rgb' => '{"r":54,"g":69,"b":79}'],
            ['name' => 'Stone',         'hex' => '#928E85', 'color_family' => 'neutral',  'temperature' => 'neutral', 'rgb' => '{"r":146,"g":142,"b":133}'],
            ['name' => 'Sand',          'hex' => '#C2A880', 'color_family' => 'neutral',  'temperature' => 'warm',    'rgb' => '{"r":194,"g":168,"b":128}'],

            // Grays
            ['name' => 'Heather Gray',  'hex' => '#9E9E9E', 'color_family' => 'gray',     'temperature' => 'neutral', 'rgb' => '{"r":158,"g":158,"b":158}'],
            ['name' => 'Light Gray',    'hex' => '#D3D3D3', 'color_family' => 'gray',     'temperature' => 'neutral', 'rgb' => '{"r":211,"g":211,"b":211}'],
            ['name' => 'Slate',         'hex' => '#708090', 'color_family' => 'gray',     'temperature' => 'cool',    'rgb' => '{"r":112,"g":128,"b":144}'],

            // Browns
            ['name' => 'Brown',         'hex' => '#7D5A50', 'color_family' => 'brown',    'temperature' => 'warm',    'rgb' => '{"r":125,"g":90,"b":80}'],
            ['name' => 'Tan',           'hex' => '#D2B48C', 'color_family' => 'brown',    'temperature' => 'warm',    'rgb' => '{"r":210,"g":180,"b":140}'],
            ['name' => 'Camel',         'hex' => '#C19A6B', 'color_family' => 'brown',    'temperature' => 'warm',    'rgb' => '{"r":193,"g":154,"b":107}'],

            // Blues
            ['name' => 'Navy',          'hex' => '#001F5B', 'color_family' => 'blue',     'temperature' => 'cool',    'rgb' => '{"r":0,"g":31,"b":91}'],
            ['name' => 'Royal Blue',    'hex' => '#4169E1', 'color_family' => 'blue',     'temperature' => 'cool',    'rgb' => '{"r":65,"g":105,"b":225}'],
            ['name' => 'Baby Blue',     'hex' => '#89CFF0', 'color_family' => 'blue',     'temperature' => 'cool',    'rgb' => '{"r":137,"g":207,"b":240}'],
            ['name' => 'Cobalt',        'hex' => '#0047AB', 'color_family' => 'blue',     'temperature' => 'cool',    'rgb' => '{"r":0,"g":71,"b":171}'],

            // Greens
            ['name' => 'Olive',         'hex' => '#6B6B2A', 'color_family' => 'green',    'temperature' => 'warm',    'rgb' => '{"r":107,"g":107,"b":42}'],
            ['name' => 'Forest Green',  'hex' => '#228B22', 'color_family' => 'green',    'temperature' => 'cool',    'rgb' => '{"r":34,"g":139,"b":34}'],
            ['name' => 'Sage',          'hex' => '#8FBC8F', 'color_family' => 'green',    'temperature' => 'cool',    'rgb' => '{"r":143,"g":188,"b":143}'],
            ['name' => 'Mint',          'hex' => '#98FF98', 'color_family' => 'green',    'temperature' => 'cool',    'rgb' => '{"r":152,"g":255,"b":152}'],
            ['name' => 'Khaki',         'hex' => '#C3B091', 'color_family' => 'green',    'temperature' => 'warm',    'rgb' => '{"r":195,"g":176,"b":145}'],

            // Reds / Pinks
            ['name' => 'Red',           'hex' => '#C8102E', 'color_family' => 'red',      'temperature' => 'warm',    'rgb' => '{"r":200,"g":16,"b":46}'],
            ['name' => 'Burgundy',      'hex' => '#800020', 'color_family' => 'red',      'temperature' => 'warm',    'rgb' => '{"r":128,"g":0,"b":32}'],
            ['name' => 'Coral',         'hex' => '#FF7F7F', 'color_family' => 'pink',     'temperature' => 'warm',    'rgb' => '{"r":255,"g":127,"b":127}'],
            ['name' => 'Hot Pink',      'hex' => '#FF69B4', 'color_family' => 'pink',     'temperature' => 'warm',    'rgb' => '{"r":255,"g":105,"b":180}'],
            ['name' => 'Blush',         'hex' => '#FFB6C1', 'color_family' => 'pink',     'temperature' => 'warm',    'rgb' => '{"r":255,"g":182,"b":193}'],

            // Yellows / Oranges
            ['name' => 'Yellow',        'hex' => '#FFD700', 'color_family' => 'yellow',   'temperature' => 'warm',    'rgb' => '{"r":255,"g":215,"b":0}'],
            ['name' => 'Orange',        'hex' => '#FF8C00', 'color_family' => 'orange',   'temperature' => 'warm',    'rgb' => '{"r":255,"g":140,"b":0}'],
            ['name' => 'Rust',          'hex' => '#B7410E', 'color_family' => 'orange',   'temperature' => 'warm',    'rgb' => '{"r":183,"g":65,"b":14}'],

            // Purples
            ['name' => 'Purple',        'hex' => '#6A0DAD', 'color_family' => 'purple',   'temperature' => 'cool',    'rgb' => '{"r":106,"g":13,"b":173}'],
            ['name' => 'Lavender',      'hex' => '#E6E6FA', 'color_family' => 'purple',   'temperature' => 'cool',    'rgb' => '{"r":230,"g":230,"b":250}'],

            // Washed / Distressed
            ['name' => 'Washed Black',  'hex' => '#2C2C2C', 'color_family' => 'neutral',  'temperature' => 'neutral', 'rgb' => '{"r":44,"g":44,"b":44}'],
            ['name' => 'Acid Wash',     'hex' => '#8B8B6B', 'color_family' => 'neutral',  'temperature' => 'neutral', 'rgb' => '{"r":139,"g":139,"b":107}'],
            ['name' => 'Vintage Wash',  'hex' => '#9B8B7B', 'color_family' => 'brown',    'temperature' => 'warm',    'rgb' => '{"r":155,"g":139,"b":123}'],

            // Seasonal / Trend
            ['name' => 'Butter Yellow', 'hex' => '#FFF9A6', 'color_family' => 'yellow',   'temperature' => 'warm',    'rgb' => '{"r":255,"g":249,"b":166}'],
            ['name' => 'Ice Blue',      'hex' => '#D6EAF8', 'color_family' => 'blue',     'temperature' => 'cool',    'rgb' => '{"r":214,"g":234,"b":248}'],
            ['name' => 'Mushroom',      'hex' => '#A5978B', 'color_family' => 'brown',    'temperature' => 'neutral', 'rgb' => '{"r":165,"g":151,"b":139}'],
            ['name' => 'Electric Blue', 'hex' => '#7DF9FF', 'color_family' => 'blue',     'temperature' => 'cool',    'rgb' => '{"r":125,"g":249,"b":255}'],
        ];

        foreach ($colors as $color) {
            DB::table('colors')->updateOrInsert(
                ['name' => $color['name']],
                array_merge($color, [
                    'hsl'        => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
