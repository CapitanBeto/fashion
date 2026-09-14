<div>

    <div class="mb-6 flex items-start justify-between">
        <div>
            <h1 class="text-lg font-medium text-zinc-100">Fashion Radar — Chile</h1>
            <p class="text-sm text-zinc-500 font-mono">{{ now()->toDateString() }} · {{ $horizonDays }}-day horizon</p>
        </div>
        @if($historicalAccuracy)
            <div class="text-right" title="Average accuracy of every real prediction evaluated against what actually happened, including historical backtests">
                <div class="font-mono text-lg text-zinc-300">{{ $historicalAccuracy['mean'] }}%</div>
                <div class="text-xs text-zinc-600">model historical accuracy (n={{ $historicalAccuracy['count'] }})</div>
            </div>
        @endif
    </div>

    @if($chileMissing)
        <div class="bg-surface-2 border border-amber-900/40 text-amber-400 rounded-lg px-4 py-3 text-sm mb-6">
            Chile is not seeded in the <code>countries</code> table — run the seeders.
        </div>
    @endif

    {{-- ── NEXT 60 DAYS ─────────────────────────────────────────────────── --}}
    <section class="mb-8">
        <h2 class="text-sm font-medium text-zinc-300 mb-3 flex items-center gap-2">
            <span>🔥</span> NEXT {{ $horizonDays }} DAYS — most likely to rise in Chile
        </h2>

        @if($nextUp->isEmpty())
            <div class="bg-surface-2 border border-border rounded-lg px-4 py-6 text-sm text-zinc-500">
                No forecasts yet. Run <code class="text-zinc-300">php artisan experiment:forecast-chile --sync</code> after an experiment has scored trends.
            </div>
        @else
            <div class="grid gap-2">
                @foreach($nextUp as $i => $prediction)
                    <a href="{{ route('trends.show', $prediction->trend->slug) }}"
                       class="flex items-center justify-between bg-surface-2 border border-border rounded-lg px-4 py-3 hover:border-border-strong transition-colors">
                        <div class="flex items-center gap-3">
                            <span class="text-zinc-600 font-mono text-sm w-5">{{ $i + 1 }}</span>
                            <div>
                                <div class="text-zinc-100 text-sm flex items-center gap-2">
                                    {{ $prediction->trend->name }}
                                    @if($prediction->trend->is_demo)
                                        <span class="px-1.5 py-0.5 rounded bg-purple-950/60 border border-purple-700/50 text-purple-300 text-[10px] font-mono tracking-wide">DEMO</span>
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-500">{{ $prediction->reasoning ? \Illuminate\Support\Str::limit($prediction->reasoning, 90) : '' }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs px-2 py-0.5 rounded border stage-{{ $prediction->trend->lifecycle_stage }}">
                                {{ $prediction->trend->lifecycle_label }}
                            </span>
                            <span class="font-mono text-lg text-emerald-400">{{ $prediction->growth_probability }}%</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <div class="grid md:grid-cols-3 gap-6">

        {{-- ── NOW ──────────────────────────────────────────────────────── --}}
        <section>
            <h2 class="text-sm font-medium text-zinc-300 mb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span> ALREADY TRENDING IN CHILE
            </h2>
            @if($now->isEmpty())
                <div class="bg-surface-2 border border-border rounded-lg px-3 py-4 text-xs text-zinc-500">No trend has reached MAINSTREAM/PEAK in Chile yet.</div>
            @else
                <div class="grid gap-2">
                    @foreach($now as $trend)
                        <a href="{{ route('trends.show', $trend->slug) }}" class="block bg-surface-2 border border-border rounded-lg px-3 py-2.5 hover:border-border-strong transition-colors">
                            <div class="text-sm text-zinc-100 flex items-center gap-2">
                                {{ $trend->name }}
                                @if($trend->is_demo)
                                    <span class="px-1.5 py-0.5 rounded bg-purple-950/60 border border-purple-700/50 text-purple-300 text-[10px] font-mono tracking-wide">DEMO</span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs px-1.5 py-0.5 rounded border stage-{{ $trend->chile_stage }}">{{ $trend->chile_stage }}</span>
                                <span class="font-mono text-sm text-zinc-300">{{ $trend->chile_strength }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── EARLY SIGNALS ────────────────────────────────────────────── --}}
        <section>
            <h2 class="text-sm font-medium text-zinc-300 mb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span> EARLY SIGNALS
            </h2>
            @if($earlySignals->isEmpty())
                <div class="bg-surface-2 border border-border rounded-lg px-3 py-4 text-xs text-zinc-500">No strong-but-not-yet-in-Chile signals detected.</div>
            @else
                <div class="grid gap-2">
                    @foreach($earlySignals as $trend)
                        <a href="{{ route('trends.show', $trend->slug) }}" class="block bg-surface-2 border border-border rounded-lg px-3 py-2.5 hover:border-border-strong transition-colors">
                            <div class="text-sm text-zinc-100 flex items-center gap-2">
                                {{ $trend->name }}
                                @if($trend->is_demo)
                                    <span class="px-1.5 py-0.5 rounded bg-purple-950/60 border border-purple-700/50 text-purple-300 text-[10px] font-mono tracking-wide">DEMO</span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-zinc-500">global momentum</span>
                                <span class="font-mono text-sm text-amber-300">{{ $trend->momentum_score }}</span>
                            </div>
                            <div class="flex items-center justify-between mt-0.5">
                                <span class="text-xs text-zinc-500">Chile presence</span>
                                <span class="font-mono text-sm text-zinc-500">{{ $trend->chile_strength ?? 0 }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── DECLINING ────────────────────────────────────────────────── --}}
        <section>
            <h2 class="text-sm font-medium text-zinc-300 mb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> LIKELY DECLINING
            </h2>
            @if($declining->isEmpty())
                <div class="bg-surface-2 border border-border rounded-lg px-3 py-4 text-xs text-zinc-500">Nothing currently classified as declining/saturated.</div>
            @else
                <div class="grid gap-2">
                    @foreach($declining as $trend)
                        <a href="{{ route('trends.show', $trend->slug) }}" class="block bg-surface-2 border border-border rounded-lg px-3 py-2.5 hover:border-border-strong transition-colors">
                            <div class="text-sm text-zinc-100 flex items-center gap-2">
                                {{ $trend->name }}
                                @if($trend->is_demo)
                                    <span class="px-1.5 py-0.5 rounded bg-purple-950/60 border border-purple-700/50 text-purple-300 text-[10px] font-mono tracking-wide">DEMO</span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs px-1.5 py-0.5 rounded border stage-{{ $trend->lifecycle_stage }}">{{ $trend->lifecycle_stage }}</span>
                                <span class="font-mono text-sm text-red-300">death prob {{ $trend->death_probability }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

    </div>

    <div class="mt-8 text-xs text-zinc-600">
        Looking for the full trend catalog? <a href="{{ route('all-trends') }}" class="text-zinc-400 hover:text-zinc-200 underline">See all trends →</a>
    </div>

</div>
