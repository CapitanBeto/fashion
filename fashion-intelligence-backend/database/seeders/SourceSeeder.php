<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Sources ─────────────────────────────────────────────────────────
        $sources = [
            [
                'name'              => 'Google Trends',
                'slug'              => 'google-trends',
                'type'              => 'google_trends',
                'reliability_score' => 85,
                'config'            => json_encode(['timeframe' => 'today 12-m', 'geo' => '', 'gprop' => '']),
                'active'            => true,
            ],
            [
                'name'              => 'Reddit',
                'slug'              => 'reddit',
                'type'              => 'reddit',
                'reliability_score' => 70,
                'config'            => json_encode(['sort' => 'top', 'time' => 'year', 'limit' => 100]),
                'active'            => true,
            ],
            [
                'name'              => 'Web Crawler',
                'slug'              => 'web-crawler',
                'type'              => 'web',
                'reliability_score' => 60,
                'config'            => json_encode(['respect_robots' => true, 'render_js' => false, 'delay_ms' => 2000]),
                'active'            => true,
            ],
            [
                'name'              => 'Apify',
                'slug'              => 'apify',
                'type'              => 'apify',
                'reliability_score' => 80,
                'config'            => json_encode(['actor_id' => null, 'dataset_id' => null]),
                'active'            => false,
            ],
            [
                'name'              => 'ScraperAPI',
                'slug'              => 'scraperapi',
                'type'              => 'scraperapi',
                'reliability_score' => 75,
                'config'            => json_encode(['render_js' => false, 'country_code' => 'us']),
                'active'            => false,
            ],
            [
                'name'              => 'Zyte',
                'slug'              => 'zyte',
                'type'              => 'zyte',
                'reliability_score' => 80,
                'config'            => json_encode(['geolocation' => 'US', 'javascript_rendering' => false]),
                'active'            => false,
            ],
            [
                'name'              => 'Instagram Data (personal API — not yet built)',
                'slug'              => 'instagram_data',
                'type'              => 'instagram_data',
                'reliability_score' => 0,
                'config'            => json_encode([
                    'posts_per_account' => 20,
                ]),
                // Placeholder, inactive until the personal instagram_data
                // API (python/scrapers/instagram_data.py) is implemented.
                // The experiment pipeline already skips it cleanly when
                // inactive (python/api/routes/experiments.py, Step 3.5)
                // instead of fabricating data.
                'active'             => false,
            ],
        ];

        foreach ($sources as $source) {
            DB::table('sources')->updateOrInsert(
                ['slug' => $source['slug']],
                array_merge($source, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // ─── Source Targets (hoodie experiment) ──────────────────────────────
        $redditId       = DB::table('sources')->where('slug', 'reddit')->value('id');
        $googleTrendsId = DB::table('sources')->where('slug', 'google-trends')->value('id');
        $webCrawlerId   = DB::table('sources')->where('slug', 'web-crawler')->value('id');
        $instagramId = DB::table('sources')
            ->where('slug', 'instagram-brightdata')
            ->value('id');
        $streetwearNicheId = DB::table('niches')->where('slug', 'streetwear')->value('id');
        $usId  = DB::table('countries')->where('iso2', 'US')->value('id');
        $gbId  = DB::table('countries')->where('iso2', 'GB')->value('id');
        $clId  = DB::table('countries')->where('iso2', 'CL')->value('id');
        $esId  = DB::table('countries')->where('iso2', 'ES')->value('id');

        // Reddit subreddits
        $subreddits = [
            ['name' => 'streetwear',         'priority' => 100],
            ['name' => 'malefashionadvice',  'priority' => 90],
            ['name' => 'fashion',            'priority' => 80],
            ['name' => 'skateboarding',      'priority' => 70],
            ['name' => 'streetwearstartup',  'priority' => 70],
            ['name' => 'frugalmalefashion',  'priority' => 60],
            ['name' => 'sneakers',           'priority' => 60],
        ];

        foreach ($subreddits as $sub) {
            DB::table('source_targets')->updateOrInsert(
                ['source_id' => $redditId, 'target_type' => 'subreddit', 'target_value' => $sub['name']],
                [
                    'source_id'    => $redditId,
                    'niche_id'     => $streetwearNicheId,
                    'country_id'   => null,
                    'target_type'  => 'subreddit',
                    'target_value' => $sub['name'],
                    'priority'     => $sub['priority'],
                    'frequency'    => 'daily',
                    'depth'        => 1,
                    'active'       => true,
                    'config'       => json_encode(['sort' => 'top', 'time' => 'year', 'limit' => 100]),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }

        // Google Trends keywords
        $keywords = [
            'oversized hoodie', 'zip hoodie', 'washed hoodie', 'heavyweight hoodie',
            'boxy hoodie', 'graphic hoodie', 'cropped hoodie', 'technical hoodie',
            'vintage hoodie', 'distressed hoodie', 'full zip hoodie',
        ];

        foreach ($keywords as $keyword) {
            foreach ([$usId, $gbId, $clId] as $countryId) {
                DB::table('source_targets')->updateOrInsert(
                    ['source_id' => $googleTrendsId, 'target_type' => 'keyword', 'target_value' => $keyword, 'country_id' => $countryId],
                    [
                        'source_id'    => $googleTrendsId,
                        'niche_id'     => $streetwearNicheId,
                        'country_id'   => $countryId,
                        'target_type'  => 'keyword',
                        'target_value' => $keyword,
                        'priority'     => 80,
                        'frequency'    => 'weekly',
                        'depth'        => 1,
                        'active'       => true,
                        'config'       => json_encode(['timeframe' => 'today 12-m']),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]
                );
            }
        }

        // Niche Spanish streetwear/fashion media (not mainstream global outlets —
        // chosen for Spain-specific relevance since Spain is the tracked source
        // market for the Chile transfer model). Each URL was verified to exist
        // and to be allowed by the site's robots.txt before being added here.
        $webArticles = [
            'https://vein.es/streetwear-8-marcas-espanolas-moda-urbana-cuenta/',
            'https://www.neo2.com/marcas-espanolas-streetwear-moda-urbana/',
            'https://fashionunited.es/tags/streetwear',
            'https://noproblembcn.com/blog/',
            'https://25gramos.com/',
            'https://fleek.25gramos.com/',
            'https://kluidmagazine.com/moda/',
            'https://highxtar.com/en/category/fashion-en/',
            'https://fuckingyoung.es/',
            'https://themedizine.com/categoria/moda',
            'https://metalmagazine.eu/',
            // Modaes.es/.com deliberately excluded: its robots.txt explicitly
            // disallows GPTBot and meta-externalagent by name — a clear
            // "we don't want AI-pipeline collection" signal, even though a
            // generic user-agent isn't literally named in the block.
        ];

        foreach ($webArticles as $url) {
            DB::table('source_targets')->updateOrInsert(
                ['source_id' => $webCrawlerId, 'target_type' => 'url', 'target_value' => $url],
                [
                    'source_id'    => $webCrawlerId,
                    'niche_id'     => $streetwearNicheId,
                    'country_id'   => $esId,
                    'target_type'  => 'url',
                    'target_value' => $url,
                    'priority'     => 70,
                    'frequency'    => 'weekly',
                    'depth'        => 1,
                    'active'       => true,
                    'config'       => json_encode([]),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }
    }
    // Instagram/Bright Data has no curated source_targets seeded — the
    // source itself is inactive (see above), so this stays an empty,
    // honest placeholder rather than a populated-but-disabled config.
}
