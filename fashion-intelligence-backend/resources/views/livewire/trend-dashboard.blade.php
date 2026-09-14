<div>

    {{-- ── Header stats ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">

        @foreach([
            ['label' => 'Total trends',   'value' => $stats['total'],   'color' => 'zinc'],
            ['label' => 'Rising',         'value' => $stats['rising'],  'color' => 'emerald'],
            ['label' => 'Emerging',       'value' => $stats['emerging'],'color' => 'blue'],
            ['label' => 'Last scraped',   'value' => $stats['last_run'] ? \Carbon\Carbon::parse($stats['last_run'])->diffForHumans() : '—', 'color' => 'zinc'],
        ] as $card)
            <div class="bg-surface-2 border border-border rounded-lg px-4 py-3">
                <div class="text-xs text-zinc-500 mb-1">{{ $card['label'] }}</div>
                <div class="font-mono text-xl font-medium
                    {{ $card['color'] === 'emerald' ? 'text-emerald-400' : ($card['color'] === 'blue' ? 'text-blue-400' : 'text-zinc-100') }}">
                    {{ $card['value'] }}
                </div>
            </div>
        @endforeach

    </div>

    {{-- ── Filter bar ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">

        {{-- Search --}}
        <div class="relative flex-1 min-w-48">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search trends..."
                class="w-full bg-surface-2 border border-border rounded-lg pl-3 pr-3 py-2 text-sm text-zinc-200
                       placeholder-zinc-600 focus:outline-none focus:border-border-strong transition-colors"
            >
        </div>

        {{-- Lifecycle filter --}}
        <select wire:model.live="lifecycleStage"
                class="bg-surface-2 border border-border rounded-lg px-3 py-2 text-sm text-zinc-300
                       focus:outline-none focus:border-border-strong">
            <option value="">All stages</option>
            @foreach(['EMERGING','EARLY_ADOPTION','ACCELERATING','MAINSTREAM','PEAK','SATURATED','DECLINING','DEAD'] as $stage)
                <option value="{{ $stage }}">{{ ucwords(strtolower(str_replace('_', ' ', $stage))) }}</option>
            @endforeach
        </select>

        {{-- Niche filter --}}
        <select wire:model.live="nicheSlug"
                class="bg-surface-2 border border-border rounded-lg px-3 py-2 text-sm text-zinc-300
                       focus:outline-none focus:border-border-strong">
            <option value="">All niches</option>
            @foreach($niches as $niche)
                <option value="{{ $niche->slug }}">{{ $niche->name }}</option>
            @endforeach
        </select>

        {{-- Sort --}}
        <select wire:model.live="sortBy"
                class="bg-surface-2 border border-border rounded-lg px-3 py-2 text-sm text-zinc-300
                       focus:outline-none focus:border-border-strong">
            <option value="commercial_opportunity_score">Sort: Opportunity</option>
            <option value="momentum_score">Sort: Momentum</option>
            <option value="growth_score">Sort: Growth</option>
            <option value="creator_score">Sort: Creators</option>
            <option value="death_probability">Sort: Death risk</option>
            <option value="first_seen_at">Sort: Newest</option>
        </select>

        {{-- Rising toggle --}}
        <label class="flex items-center gap-2 cursor-pointer select-none">
            <div class="relative">
                <input type="checkbox" wire:model.live="risingOnly" class="sr-only peer">
                <div class="w-8 h-4 bg-surface-3 peer-checked:bg-emerald-600 rounded-full transition-colors border border-border"></div>
                <div class="absolute top-0.5 left-0.5 w-3 h-3 bg-zinc-400 peer-checked:bg-white rounded-full transition-all peer-checked:translate-x-4"></div>
            </div>
            <span class="text-xs text-zinc-400">Rising only</span>
        </label>

    </div>

    {{-- ── Trends table ─────────────────────────────────────────────────────── --}}
    <div class="bg-surface-1 border border-border rounded-xl overflow-hidden">

        {{-- Table header --}}
        <div class="grid grid-cols-12 gap-2 px-4 py-2.5 border-b border-border text-xs text-zinc-500 font-medium uppercase tracking-wide">
            <div class="col-span-4">Trend</div>
            <div class="col-span-2">Stage</div>
            <div class="col-span-2 text-right">Opportunity</div>
            <div class="col-span-1 text-right">Momentum</div>
            <div class="col-span-1 text-right hidden lg:block">Brands</div>
            <div class="col-span-1 text-right hidden lg:block">Creators</div>
            <div class="col-span-1 text-right">Death risk</div>
        </div>

        {{-- Rows --}}
        @forelse($trends as $trend)
            <a href="{{ route('trends.show', $trend->slug) }}"
               class="grid grid-cols-12 gap-2 px-4 py-3 border-b border-border last:border-0
                      hover:bg-surface-2 transition-colors group">

                {{-- Name + niche --}}
                <div class="col-span-4 min-w-0">
                    <div class="text-sm font-medium text-zinc-100 truncate group-hover:text-white">
                        {{ $trend->name }}
                    </div>
                    <div class="text-xs text-zinc-500 mt-0.5 flex items-center gap-1.5">
                        @if($trend->niche)
                            <span>{{ $trend->niche->name }}</span>
                            <span>·</span>
                        @endif
                        @if($trend->primaryCountry)
                            <span>{{ $trend->primaryCountry->iso2 }}</span>
                        @endif
                        @if($trend->detection_method === 'emergent')
                            <span class="px-1 py-0.5 rounded bg-blue-500/10 text-blue-400 text-xs border border-blue-500/20">emergent</span>
                        @endif
                    </div>
                </div>

                {{-- Stage pill --}}
                <div class="col-span-2 flex items-center">
                    <span class="px-2 py-0.5 rounded-full text-xs border stage-{{ $trend->lifecycle_stage }}">
                        {{ $trend->lifecycle_label }}
                    </span>
                </div>

                {{-- Opportunity score --}}
                <div class="col-span-2 flex items-center justify-end gap-2">
                    <div class="flex-1 max-w-16 hidden xl:block">
                        <div class="h-1 bg-surface-3 rounded-full overflow-hidden">
                            <div class="h-full rounded-full score-bar-fill
                                {{ $trend->commercial_opportunity_score >= 70 ? 'bg-emerald-500' :
                                   ($trend->commercial_opportunity_score >= 40 ? 'bg-amber-500' : 'bg-zinc-600') }}"
                                 style="width: {{ $trend->commercial_opportunity_score }}%"></div>
                        </div>
                    </div>
                    <span class="font-mono text-sm font-medium
                        {{ $trend->commercial_opportunity_score >= 70 ? 'text-emerald-400' :
                           ($trend->commercial_opportunity_score >= 40 ? 'text-amber-400' : 'text-zinc-400') }}">
                        {{ $trend->commercial_opportunity_score }}
                    </span>
                </div>

                {{-- Momentum --}}
                <div class="col-span-1 flex items-center justify-end">
                    <span class="font-mono text-sm text-zinc-300">{{ $trend->momentum_score }}</span>
                </div>

                {{-- Brands --}}
                <div class="col-span-1 hidden lg:flex items-center justify-end">
                    <span class="font-mono text-sm text-zinc-500">{{ $trend->brands_count }}</span>
                </div>

                {{-- Creators --}}
                <div class="col-span-1 hidden lg:flex items-center justify-end">
                    <span class="font-mono text-sm text-zinc-500">{{ $trend->creators_count }}</span>
                </div>

                {{-- Death probability --}}
                <div class="col-span-1 flex items-center justify-end">
                    <span class="font-mono text-sm {{ $trend->death_probability >= 50 ? 'text-red-400' : 'text-zinc-600' }}">
                        {{ $trend->death_probability > 0 ? $trend->death_probability . '%' : '—' }}
                    </span>
                </div>

            </a>
        @empty
            <div class="px-4 py-16 text-center">
                <div class="text-zinc-600 text-sm">
                    @if($search || $lifecycleStage || $nicheSlug)
                        No trends match your filters.
                    @else
                        No trends yet. Run your first experiment:
                        <code class="ml-1 px-1.5 py-0.5 bg-surface-3 rounded text-zinc-400 text-xs">
                            php artisan experiment:run hoodie_test_01
                        </code>
                    @endif
                </div>
            </div>
        @endforelse

    </div>

    {{-- Pagination --}}
    @if($trends->hasPages())
        <div class="mt-4">
            {{ $trends->links('livewire.pagination') }}
        </div>
    @endif

    {{-- Result count --}}
    <div class="mt-2 text-xs text-zinc-600 text-right">
        {{ number_format($trends->total()) }} trends
        @if($trends->hasPages())
            · page {{ $trends->currentPage() }} of {{ $trends->lastPage() }}
        @endif
    </div>

</div>
