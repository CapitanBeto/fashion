<div>

    {{-- ── Breadcrumb + actions ──────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2 text-sm text-zinc-500">
            <a href="{{ route('dashboard') }}" class="hover:text-zinc-300 transition-colors">Fashion Radar</a>
            <span>/</span>
            <span class="text-zinc-300">{{ $trend->name }}</span>
        </div>
        <button wire:click="rescore"
                wire:loading.attr="disabled"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-3 border border-border
                       text-xs text-zinc-400 hover:text-zinc-200 hover:border-border-strong transition-all disabled:opacity-50">
            <svg wire:loading wire:target="rescore" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Recalculate scores
        </button>
    </div>

    {{-- ── Title + stage ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">{{ $trend->name }}</h1>
            <div class="mt-1 flex items-center gap-2 text-sm text-zinc-500">
                @if($trend->niche) <span>{{ $trend->niche->name }}</span> @endif
                @if($trend->primaryCountry) <span>·</span><span>{{ $trend->primaryCountry->name }}</span> @endif
                <span>·</span>
                <span>First seen {{ $trend->first_seen_at?->diffForHumans() }}</span>
            </div>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-sm border stage-{{ $trend->lifecycle_stage }}">
                {{ $trend->lifecycle_label }}
            </span>
            @if($trend->detection_method === 'emergent')
                <span class="px-2 py-1 rounded-full text-xs border border-blue-500/30 bg-blue-500/10 text-blue-400">emergent</span>
            @endif
            @if($trend->is_demo)
                <span class="px-2 py-1 rounded-full text-xs border border-purple-700/50 bg-purple-950/60 text-purple-300 font-mono tracking-wide">DEMO DATA</span>
            @endif
        </div>
    </div>

    @if($trend->is_demo)
        <div class="bg-purple-950/30 border border-purple-800/40 text-purple-300 rounded-lg px-4 py-2.5 text-sm mb-6">
            This is synthetic example data for previewing the dashboard — not derived from live scraping.
        </div>
    @endif

    {{-- ── Description ─────────────────────────────────────────────────────────── --}}
    @if($trend->description)
        <p class="text-sm text-zinc-400 leading-relaxed mb-6 max-w-3xl">{{ $trend->description }}</p>
    @endif

    {{-- ── Scores grid ─────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2 mb-6">
        @foreach([
            ['label' => 'Opportunity', 'value' => $trend->commercial_opportunity_score, 'highlight' => true],
            ['label' => 'Momentum',    'value' => $trend->momentum_score],
            ['label' => 'Growth',      'value' => $trend->growth_score],
            ['label' => 'Search',      'value' => $trend->search_score],
            ['label' => 'Creators',    'value' => $trend->creator_score],
            ['label' => 'Brands',      'value' => $trend->brand_score],
            ['label' => 'Convergence', 'value' => $trend->convergence_score],
            ['label' => 'Death risk',  'value' => $trend->death_probability, 'danger' => true],
        ] as $score)
            <div class="bg-surface-2 border border-border rounded-lg p-3
                        {{ ($score['highlight'] ?? false) ? 'border-emerald-800/50 bg-emerald-950/30' : '' }}
                        {{ ($score['danger'] ?? false) && $score['value'] >= 50 ? 'border-red-800/50 bg-red-950/20' : '' }}">
                <div class="text-xs text-zinc-500 mb-1.5">{{ $score['label'] }}</div>
                <div class="font-mono text-xl font-medium
                    {{ ($score['highlight'] ?? false) ? 'text-emerald-400' :
                       (($score['danger'] ?? false) && $score['value'] >= 50 ? 'text-red-400' : 'text-zinc-200') }}">
                    {{ $score['value'] }}
                </div>
                {{-- Mini score bar --}}
                <div class="mt-2 h-0.5 bg-surface-3 rounded-full overflow-hidden">
                    <div class="h-full rounded-full score-bar-fill
                        {{ ($score['danger'] ?? false) ? 'bg-red-500' :
                           (($score['highlight'] ?? false) ? 'bg-emerald-500' : 'bg-zinc-500') }}"
                         style="width: {{ $score['value'] }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Chile 60-day forecast ───────────────────────────────────────────── --}}
    @php $forecast = $trend->predictions->first(); @endphp
    @if($forecast)
        <div class="bg-surface-1 border border-emerald-800/40 rounded-xl p-5 mb-6">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-xs font-medium text-emerald-400 uppercase tracking-wide mb-1">
                        Chile {{ $forecast->prediction_horizon }}-day forecast
                    </h2>
                    <div class="text-xs text-zinc-500">
                        Predicted {{ $forecast->prediction_date?->format('M j, Y') }} · window ends {{ $forecast->target_date?->format('M j, Y') }}
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="font-mono text-3xl font-medium text-emerald-400">{{ $forecast->growth_probability }}%</div>
                    <div class="text-xs text-zinc-600">{{ $forecast->confidence }}% confidence</div>
                    @if($historicalAccuracy)
                        <div class="text-xs text-zinc-600 mt-0.5" title="Average accuracy of every real prediction this system has evaluated against what actually happened, including historical backtests">
                            model historically {{ $historicalAccuracy['mean'] }}% accurate
                            <span class="text-zinc-700">(n={{ $historicalAccuracy['count'] }})</span>
                        </div>
                    @else
                        <div class="text-xs text-zinc-700 mt-0.5">no evaluated predictions yet</div>
                    @endif
                </div>
            </div>

            @php $snap = $forecast->input_snapshot ?? []; @endphp

            {{-- Current vs predicted Chile stage --}}
            <div class="flex items-center gap-3 mb-4 text-sm">
                <span class="text-zinc-500">Current Chile stage</span>
                <span class="px-2 py-0.5 rounded border text-xs stage-{{ $snap['current_chile_stage'] ?? 'EMERGING' }}">{{ $snap['current_chile_stage'] ?? '—' }}</span>
                <span class="text-zinc-600">→</span>
                <span class="text-zinc-500">Predicted</span>
                <span class="px-2 py-0.5 rounded border text-xs stage-{{ $snap['predicted_stage'] ?? 'EMERGING' }}">{{ $snap['predicted_stage'] ?? '—' }}</span>
            </div>

            {{-- Horizon curve --}}
            @if(!empty($snap['horizon_curve']))
                <div class="flex items-end gap-4 mb-4 pb-4 border-b border-border">
                    @foreach($snap['horizon_curve'] as $days => $prob)
                        <div class="text-center">
                            <div class="font-mono text-sm text-zinc-300">{{ $prob }}%</div>
                            <div class="text-xs text-zinc-600">{{ $days }}d</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="grid md:grid-cols-2 gap-4">
                {{-- Why --}}
                <div>
                    <h3 class="text-xs font-medium text-zinc-400 uppercase tracking-wide mb-2">Why</h3>
                    <ul class="space-y-1.5">
                        @foreach($snap['evidence'] ?? [] as $point)
                            <li class="text-sm text-zinc-400 flex gap-2"><span class="text-emerald-500">+</span> {{ $point }}</li>
                        @endforeach
                    </ul>
                </div>

                {{-- Contradictions --}}
                <div>
                    <h3 class="text-xs font-medium text-zinc-400 uppercase tracking-wide mb-2">Contradicting evidence</h3>
                    @if(!empty($snap['contradictions']))
                        <ul class="space-y-1.5">
                            @foreach($snap['contradictions'] as $point)
                                <li class="text-sm text-zinc-400 flex gap-2"><span class="text-red-500">−</span> {{ $point }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-zinc-600">None detected.</p>
                    @endif
                </div>
            </div>

            {{-- Sub-scores --}}
            <div class="mt-4 pt-4 border-t border-border grid grid-cols-3 md:grid-cols-7 gap-3 text-center">
                @foreach([
                    'Global mom.' => $snap['global_momentum'] ?? null,
                    'Chile mom.' => $snap['chile_momentum'] ?? null,
                    'Convergence' => $snap['cross_source_convergence'] ?? null,
                    'Creators (CL)' => $snap['creator_adoption'] ?? null,
                    'Brands (CL)' => $snap['brand_adoption'] ?? null,
                    'Search growth' => $snap['search_growth'] ?? null,
                    'Transfer sim.' => $snap['historical_transfer_similarity'] ?? null,
                ] as $label => $value)
                    <div>
                        <div class="font-mono text-sm text-zinc-300">{{ $value ?? '—' }}</div>
                        <div class="text-xs text-zinc-600">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-surface-2 border border-border rounded-xl p-4 mb-6 text-sm text-zinc-500">
            No Chile forecast yet. Run <code class="text-zinc-300">php artisan experiment:forecast-chile --sync</code>.
        </div>
    @endif

    {{-- ── Two-column layout ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

        {{-- Left col: Snapshots chart (momentum over time) --}}
        <div class="lg:col-span-2 bg-surface-1 border border-border rounded-xl p-4">
            <h2 class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-4">Momentum over time</h2>

            @php
                $snapshots = $trend->snapshots->take(-52); // max 52 weeks
            @endphp

            @if($snapshots->count() >= 2)
                <div class="relative h-32" id="sparkline-container">
                    <canvas id="momentum-chart" class="w-full h-full"></canvas>
                </div>
                <div class="mt-2 flex justify-between text-xs text-zinc-600">
                    <span>{{ $snapshots->first()->snapshot_date->format('M j') }}</span>
                    <span>{{ $snapshots->last()->snapshot_date->format('M j, Y') }}</span>
                </div>

                <script>
                (function() {
                    const labels = @json($snapshots->pluck('snapshot_date')->map(fn($d) => $d->format('M j')));
                    const values = @json($snapshots->pluck('momentum_score'));
                    const canvas = document.getElementById('momentum-chart');
                    const ctx = canvas.getContext('2d');
                    const w = canvas.offsetWidth;
                    const h = canvas.offsetHeight;
                    canvas.width = w;
                    canvas.height = h;

                    const max = Math.max(...values, 1);
                    const min = Math.min(...values, 0);
                    const range = max - min || 1;
                    const pad = { t: 4, b: 4, l: 4, r: 4 };
                    const points = values.map((v, i) => ({
                        x: pad.l + (i / (values.length - 1)) * (w - pad.l - pad.r),
                        y: h - pad.b - ((v - min) / range) * (h - pad.t - pad.b),
                    }));

                    // Fill
                    ctx.beginPath();
                    ctx.moveTo(points[0].x, h - pad.b);
                    points.forEach(p => ctx.lineTo(p.x, p.y));
                    ctx.lineTo(points[points.length - 1].x, h - pad.b);
                    ctx.closePath();
                    const grad = ctx.createLinearGradient(0, 0, 0, h);
                    grad.addColorStop(0, 'rgba(52,211,153,0.18)');
                    grad.addColorStop(1, 'rgba(52,211,153,0)');
                    ctx.fillStyle = grad;
                    ctx.fill();

                    // Line
                    ctx.beginPath();
                    points.forEach((p, i) => i === 0 ? ctx.moveTo(p.x, p.y) : ctx.lineTo(p.x, p.y));
                    ctx.strokeStyle = '#34d399';
                    ctx.lineWidth = 1.5;
                    ctx.lineJoin = 'round';
                    ctx.stroke();
                })();
                </script>
            @else
                <div class="h-32 flex items-center justify-center text-xs text-zinc-600">
                    Not enough data yet. Snapshots build up over time as the experiment runs.
                </div>
            @endif
        </div>

        {{-- Right col: Geography --}}
        <div class="bg-surface-1 border border-border rounded-xl p-4">
            <h2 class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-3">Countries</h2>
            <div class="space-y-2">
                @forelse($trend->countries->sortByDesc(fn($c) => $c->pivot->strength)->take(8) as $country)
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-zinc-300 w-8 font-mono text-xs">{{ $country->iso2 }}</span>
                        <div class="flex-1 h-1 bg-surface-3 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full score-bar-fill"
                                 style="width: {{ $country->pivot->strength }}%"></div>
                        </div>
                        <span class="text-xs font-mono text-zinc-500 w-6 text-right">{{ $country->pivot->strength }}</span>
                        @if($country->pivot->stage)
                            <span class="text-xs px-1 py-0.5 rounded border stage-{{ $country->pivot->stage }}">
                                {{ substr($country->pivot->stage, 0, 3) }}
                            </span>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-zinc-600">No geographic data yet.</p>
                @endforelse
            </div>

            {{-- Counts --}}
            <div class="mt-4 pt-3 border-t border-border grid grid-cols-3 gap-2 text-center">
                @foreach([
                    ['label' => 'brands',   'count' => $trend->brands_count],
                    ['label' => 'creators', 'count' => $trend->creators_count],
                    ['label' => 'mentions', 'count' => $trend->mentions_count],
                ] as $c)
                    <div>
                        <div class="font-mono text-base font-medium text-zinc-200">{{ $c['count'] }}</div>
                        <div class="text-xs text-zinc-600">{{ $c['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- ── Attributes row ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

        {{-- Colors --}}
        <div class="bg-surface-1 border border-border rounded-xl p-4">
            <h2 class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-3">Colors</h2>
            <div class="flex flex-wrap gap-2">
                @forelse($trend->colors->take(8) as $color)
                    <div class="flex items-center gap-1.5" title="{{ $color->name }} ({{ $color->pivot->frequency }}×)">
                        @if($color->hex)
                            <div class="w-4 h-4 rounded border border-border-strong flex-shrink-0"
                                 style="background-color: {{ $color->hex }}"></div>
                        @endif
                        <span class="text-xs text-zinc-400">{{ $color->name }}</span>
                        <span class="text-xs text-zinc-600 font-mono">{{ $color->pivot->frequency }}</span>
                    </div>
                @empty
                    <p class="text-xs text-zinc-600">No color data yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Silhouettes --}}
        <div class="bg-surface-1 border border-border rounded-xl p-4">
            <h2 class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-3">Silhouettes</h2>
            <div class="space-y-1.5">
                @forelse($trend->silhouettes->take(6) as $sil)
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-zinc-300 flex-1">{{ $sil->name }}</span>
                        <div class="w-20 h-1 bg-surface-3 rounded-full overflow-hidden">
                            <div class="h-full bg-purple-500 rounded-full"
                                 style="width: {{ min($sil->pivot->percentage ?? 0, 100) }}%"></div>
                        </div>
                        <span class="text-xs font-mono text-zinc-500 w-8 text-right">{{ $sil->pivot->frequency }}</span>
                    </div>
                @empty
                    <p class="text-xs text-zinc-600">No silhouette data yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Graphics --}}
        <div class="bg-surface-1 border border-border rounded-xl p-4">
            <h2 class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-3">Graphics</h2>
            <div class="space-y-1.5">
                @forelse($trend->trendGraphics->take(6) as $graphic)
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-zinc-300 flex-1">{{ $graphic->name }}</span>
                        <span class="text-xs font-mono text-zinc-500">{{ $graphic->pivot->frequency }}×</span>
                    </div>
                @empty
                    <p class="text-xs text-zinc-600">No graphic data yet.</p>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ── Commercial opportunities ────────────────────────────────────────── --}}
    @if($trend->opportunities->isNotEmpty())
        <div class="mb-4">
            <h2 class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-3">Commercial opportunities</h2>
            <div class="space-y-3">
                @foreach($trend->opportunities->take(3) as $opp)
                    <div class="bg-surface-1 border border-border rounded-xl p-4
                                {{ $opp->opportunity_score >= 70 ? 'border-emerald-800/40' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                @if($opp->suggested_product_name)
                                    <div class="font-medium text-zinc-100 mb-1">{{ $opp->suggested_product_name }}</div>
                                @endif
                                @if($opp->suggested_target_audience)
                                    <div class="text-sm text-zinc-400 mb-2">{{ $opp->suggested_target_audience }}</div>
                                @endif
                                @if($opp->reasoning)
                                    <p class="text-sm text-zinc-500 leading-relaxed">{{ $opp->reasoning }}</p>
                                @endif

                                {{-- Product details --}}
                                <div class="mt-3 flex flex-wrap gap-3 text-xs text-zinc-500">
                                    @if($opp->suggested_silhouette)
                                        <span>{{ $opp->suggested_silhouette }}</span>
                                    @endif
                                    @if($opp->suggested_price_min && $opp->suggested_price_max)
                                        <span>·</span>
                                        <span>${{ number_format($opp->suggested_price_min) }}–${{ number_format($opp->suggested_price_max) }}</span>
                                    @endif
                                    @if($opp->country)
                                        <span>·</span>
                                        <span>{{ $opp->country->name }}</span>
                                    @endif
                                    @if(!empty($opp->suggested_colors))
                                        <span>·</span>
                                        <span>{{ implode(', ', array_slice($opp->suggested_colors, 0, 3)) }}</span>
                                    @endif
                                </div>

                                {{-- Risks --}}
                                @if(!empty($opp->risks))
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach(array_slice($opp->risks, 0, 3) as $risk)
                                            <span class="px-2 py-0.5 rounded bg-red-950/40 border border-red-800/30 text-red-400 text-xs">
                                                {{ $risk }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Score badge --}}
                            <div class="text-center flex-shrink-0">
                                <div class="font-mono text-2xl font-medium
                                    {{ $opp->opportunity_score >= 70 ? 'text-emerald-400' :
                                       ($opp->opportunity_score >= 40 ? 'text-amber-400' : 'text-zinc-400') }}">
                                    {{ $opp->opportunity_score }}
                                </div>
                                <div class="text-xs text-zinc-600">score</div>
                                <div class="mt-1 text-xs text-zinc-600">{{ $opp->confidence }}% conf.</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Keywords ─────────────────────────────────────────────────────────── --}}
    @if(!empty($trend->keywords))
        <div class="bg-surface-1 border border-border rounded-xl p-4">
            <h2 class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-3">Keywords</h2>
            <div class="flex flex-wrap gap-1.5">
                @foreach($trend->keywords as $keyword)
                    <span class="px-2 py-0.5 rounded bg-surface-3 border border-border text-xs text-zinc-400 font-mono">
                        {{ $keyword }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

</div>
