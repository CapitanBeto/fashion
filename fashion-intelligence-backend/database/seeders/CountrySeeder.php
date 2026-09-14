<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            // MVP experiment countries (priority 100)
            ['name' => 'United States',  'iso2' => 'US', 'iso3' => 'USA', 'language' => 'en', 'currency' => 'USD', 'priority' => 100],
            ['name' => 'United Kingdom', 'iso2' => 'GB', 'iso3' => 'GBR', 'language' => 'en', 'currency' => 'GBP', 'priority' => 100],
            ['name' => 'Chile',          'iso2' => 'CL', 'iso3' => 'CHL', 'language' => 'es', 'currency' => 'CLP', 'priority' => 100],

            // Secondary markets (priority 50)
            ['name' => 'Australia',      'iso2' => 'AU', 'iso3' => 'AUS', 'language' => 'en', 'currency' => 'AUD', 'priority' => 50],
            ['name' => 'Canada',         'iso2' => 'CA', 'iso3' => 'CAN', 'language' => 'en', 'currency' => 'CAD', 'priority' => 50],
            ['name' => 'France',         'iso2' => 'FR', 'iso3' => 'FRA', 'language' => 'fr', 'currency' => 'EUR', 'priority' => 50],
            ['name' => 'Germany',        'iso2' => 'DE', 'iso3' => 'DEU', 'language' => 'de', 'currency' => 'EUR', 'priority' => 50],
            ['name' => 'Japan',          'iso2' => 'JP', 'iso3' => 'JPN', 'language' => 'ja', 'currency' => 'JPY', 'priority' => 50],
            ['name' => 'South Korea',    'iso2' => 'KR', 'iso3' => 'KOR', 'language' => 'ko', 'currency' => 'KRW', 'priority' => 50],
            ['name' => 'Brazil',         'iso2' => 'BR', 'iso3' => 'BRA', 'language' => 'pt', 'currency' => 'BRL', 'priority' => 50],
            ['name' => 'Italy',          'iso2' => 'IT', 'iso3' => 'ITA', 'language' => 'it', 'currency' => 'EUR', 'priority' => 30],
            ['name' => 'Spain',          'iso2' => 'ES', 'iso3' => 'ESP', 'language' => 'es', 'currency' => 'EUR', 'priority' => 30],
            ['name' => 'Mexico',         'iso2' => 'MX', 'iso3' => 'MEX', 'language' => 'es', 'currency' => 'MXN', 'priority' => 30],
            ['name' => 'Netherlands',    'iso2' => 'NL', 'iso3' => 'NLD', 'language' => 'nl', 'currency' => 'EUR', 'priority' => 30],
            ['name' => 'Sweden',         'iso2' => 'SE', 'iso3' => 'SWE', 'language' => 'sv', 'currency' => 'SEK', 'priority' => 20],
        ];

        foreach ($countries as $country) {
            DB::table('countries')->updateOrInsert(
                ['iso2' => $country['iso2']],
                array_merge($country, [
                    'active'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
