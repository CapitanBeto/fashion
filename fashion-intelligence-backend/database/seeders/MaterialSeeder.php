<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            'french-terry'    => 'French Terry',
            'fleece'          => 'Fleece',
            'heavyweight-cotton' => 'Heavyweight Cotton',
            'midweight-cotton'   => 'Midweight Cotton',
            'waffle-knit'     => 'Waffle Knit',
            'ribbed-knit'     => 'Ribbed Knit',
            'jersey'          => 'Jersey',
            'nylon'           => 'Nylon',
            'polyester'       => 'Polyester',
            'cotton-poly-blend' => 'Cotton / Poly Blend',
            'linen'           => 'Linen',
            'denim'           => 'Denim',
            'velour'          => 'Velour',
            'velvet'          => 'Velvet',
            'corduroy'        => 'Corduroy',
            'twill'           => 'Twill',
            'canvas'          => 'Canvas',
            'mesh'            => 'Mesh',
            'sherpa'          => 'Sherpa',
            'cashmere'        => 'Cashmere',
            'merino-wool'     => 'Merino Wool',
            'wool'            => 'Wool',
            'leather'         => 'Leather',
            'faux-leather'    => 'Faux Leather',
            'satin'           => 'Satin',
            'silk'            => 'Silk',
            'recycled-polyester' => 'Recycled Polyester',
            'organic-cotton'  => 'Organic Cotton',
        ];

        foreach ($materials as $slug => $name) {
            DB::table('materials')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
