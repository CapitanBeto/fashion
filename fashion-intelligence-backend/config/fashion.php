<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fashion Intelligence Core Configuration
    |--------------------------------------------------------------------------
    |
    | All configurable parameters for the platform. Never hardcode these
    | values — always read from this config which reads from .env.
    |
    */

    // ─── Python Engine ───────────────────────────────────────────────────────
    'python_engine' => [
        'url'     => env('PYTHON_ENGINE_URL', 'http://localhost:8001'),
        'secret'  => env('PYTHON_ENGINE_SECRET'),
        'timeout' => (int) env('PYTHON_ENGINE_TIMEOUT', 120),
    ],

    // ─── LLM ─────────────────────────────────────────────────────────────────
    'llm' => [
        'provider'       => env('LLM_PROVIDER', 'ollama'),
        'model'          => env('LLM_MODEL', 'qwen3:8b'),
        'base_url'       => env('LLM_BASE_URL', 'http://localhost:11434'),
        'max_tokens'     => (int) env('LLM_MAX_TOKENS', 2048),
        'temperature'    => (float) env('LLM_TEMPERATURE', 0.1),
        'context_window' => (int) env('LLM_CONTEXT_WINDOW', 8192),
    ],

    // ─── Scraping ────────────────────────────────────────────────────────────
    'scraping' => [
        'mode'              => env('SCRAPE_MODE', 'FREE'), // FREE | MANAGED | FULL
        'max_cost_usd'      => (float) env('SCRAPE_MAX_COST_USD', 0.00),
        'max_requests'      => (int) env('SCRAPE_MAX_REQUESTS', 500),
        'max_records'       => (int) env('SCRAPE_MAX_RECORDS', 5000),
        'max_images'        => (int) env('SCRAPE_MAX_IMAGES', 200),
        'max_runtime_min'   => (int) env('SCRAPE_MAX_RUNTIME_MINUTES', 60),
        'default_delay_ms'  => (int) env('SCRAPE_DEFAULT_DELAY_MS', 2000),
        'max_concurrency'   => (int) env('SCRAPE_MAX_CONCURRENCY', 3),
        'retry_count'       => (int) env('SCRAPE_RETRY_COUNT', 3),
        'timeout_seconds'   => (int) env('SCRAPE_TIMEOUT_SECONDS', 30),
        'respect_robots'    => env('SCRAPE_RESPECT_ROBOTS_TXT', true),
        'download_images'   => env('SCRAPE_DOWNLOAD_IMAGES', true),
        'render_js'         => env('SCRAPE_RENDER_JS', false),

        'providers' => [
            'apify' => [
                'api_token'     => env('APIFY_API_TOKEN'),
                'actor_id'      => env('APIFY_DEFAULT_ACTOR_ID'),
                'dataset_id'    => env('APIFY_DEFAULT_DATASET_ID'),
                'cost_per_req'  => 0.000_50, // approximate
            ],
            'scraperapi' => [
                'api_key'       => env('SCRAPERAPI_KEY'),
                'cost_per_req'  => 0.000_18,
            ],
            'zyte' => [
                'api_key'       => env('ZYTE_API_KEY'),
                'cost_per_req'  => 0.003_00,
            ],
            'oxylabs' => [
                'username'      => env('OXYLABS_USERNAME'),
                'password'      => env('OXYLABS_PASSWORD'),
                'cost_per_req'  => 0.001_50,
            ],
            'decodo' => [
                'username'      => env('DECODO_USERNAME'),
                'password'      => env('DECODO_PASSWORD'),
                'cost_per_req'  => 0.001_00,
            ],
        ],
    ],

    // ─── Reddit ──────────────────────────────────────────────────────────────
    'reddit' => [
        'client_id'     => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
        'user_agent'    => env('REDDIT_USER_AGENT', 'FashionIntelligence/1.0'),
        'rate_limit'    => 60, // requests per minute (Reddit API limit)
    ],

    // ─── Demo ─────────────────────────────────────────────────────────────────
    'demo' => [
        'enabled'   => env('DEMO_MODE', false),
        'data_path' => env('DEMO_DATA_PATH', 'experiments/demo/'),
    ],

    // ─── Lifecycle Stage Thresholds ──────────────────────────────────────────
    // These are defaults — overridden by scoring_parameters table at runtime
    'lifecycle' => [
        'emerging_upper'     => 20,
        'early_adoption_upper' => 40,
        'accelerating_upper' => 65,
        'mainstream_upper'   => 80,
        'peak_upper'         => 90,
        'saturated_upper'    => 75, // momentum dropping but count high
        'decline_upper'      => 40,
        'death_threshold'    => 15,
    ],

    // ─── Default Scoring Weights ─────────────────────────────────────────────
    // These are defaults — overridden by scoring_parameters table at runtime
    'scoring' => [
        'commercial_opportunity' => [
            'momentum_weight'            => 0.20,
            'demand_weight'              => 0.15,
            'creator_adoption_weight'    => 0.10,
            'sales_signal_weight'        => 0.15,
            'competition_inverse_weight' => 0.15,
            'growth_weight'              => 0.10,
            'longevity_weight'           => 0.05,
            'brand_fit_weight'           => 0.05,
            'novelty_weight'             => 0.05,
        ],
        'momentum' => [
            'growth_velocity_weight'     => 0.30,
            'growth_acceleration_weight' => 0.20,
            'search_growth_weight'       => 0.20,
            'social_growth_weight'       => 0.10,
            'creator_adoption_weight'    => 0.10,
            'brand_adoption_weight'      => 0.05,
            'geographic_spread_weight'   => 0.05,
        ],
        'convergence' => [
            'google_trends_weight' => 0.25,
            'reddit_weight'        => 0.20,
            'brand_weight'         => 0.20,
            'creator_weight'       => 0.15,
            'ecommerce_weight'     => 0.10,
            'blog_weight'          => 0.05,
            'other_weight'         => 0.05,
        ],
    ],

    // ─── Experiments ─────────────────────────────────────────────────────────
    'experiments' => [
        'hoodie_test_01' => [
            'name'        => 'HOODIE_TREND_TEST_01',
            // Spain as the tracked source-market (culturally/linguistically closer
            // precursor to Chile than the US/UK — matches the diffusion-path
            // examples in the spec, e.g. UK -> Spain -> Chile) plus Chile itself
            // to measure local presence directly.
            'countries'   => ['ES', 'CL'],
            'niche'       => 'streetwear',
            'period_days' => 180,
            'sources'     => ['google_trends', 'reddit', 'web'],
            'max_brands'  => 100,
            'max_creators' => 100,
            'max_records' => 5000,
            'keywords'    => [
                'oversized hoodie', 'zip hoodie', 'washed hoodie',
                'heavyweight hoodie', 'boxy hoodie', 'graphic hoodie',
                'cropped hoodie', 'technical hoodie', 'vintage hoodie',
                'distressed hoodie', 'full zip hoodie',
                // Broader niche-scene keyword: the Spanish streetwear media
                // sites scraped in Step 3 mostly cover brands/culture rather
                // than specific hoodie silhouettes, so without this, real
                // extracted entities (brand names, "DIY culture" style trend
                // concepts) had no keyword to be credited against at all —
                // confirmed directly by testing extraction on real content.
                'streetwear',
            ],
            // Spanish-language variants matched against the SAME trend as their
            // English key — the trend keeps its English name (Google Trends is
            // queried in English), but entity extraction on scraped Spanish
            // media (vein.es, neo2.com, etc.) needs these to actually credit
            // mentions instead of silently matching nothing.
            'keyword_translations' => [
                'oversized hoodie'   => ['sudadera oversized', 'sudadera holgada'],
                'zip hoodie'         => ['sudadera con cremallera', 'sudadera cremallera'],
                'washed hoodie'      => ['sudadera lavada', 'sudadera desgastada'],
                'heavyweight hoodie' => ['sudadera gruesa', 'sudadera pesada'],
                'boxy hoodie'        => ['sudadera boxy', 'sudadera cuadrada'],
                'graphic hoodie'     => ['sudadera estampada', 'sudadera con grafico'],
                'cropped hoodie'     => ['sudadera corta', 'sudadera cropped'],
                'technical hoodie'   => ['sudadera tecnica'],
                'vintage hoodie'     => ['sudadera vintage', 'sudadera retro'],
                'distressed hoodie'  => ['sudadera rota', 'sudadera destruida'],
                'full zip hoodie'    => ['sudadera cremallera completa', 'sudadera full zip'],
                'streetwear'         => ['moda urbana', 'cultura urbana', 'ropa urbana'],
            ],
            'subreddits' => [
                'streetwear', 'malefashionadvice', 'fashion',
                'skateboarding', 'streetwearstartup',
                'frugalmalefashion', 'sneakers',
            ],
        ],
    ],

    // ─── Signal Type Weights ─────────────────────────────────────────────────
    'signal_reliability' => [
        'google_trends'  => 85,
        'reddit'         => 70,
        'brand_official' => 80,
        'creator_post'   => 65,
        'blog_editorial' => 60,
        'ecommerce'      => 75,
        'forum'          => 50,
        'social_comment' => 40,
    ],

];
