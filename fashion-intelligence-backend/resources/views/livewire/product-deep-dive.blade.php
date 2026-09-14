<div>

    <div class="mb-6">
        <h1 class="text-lg font-medium text-zinc-100">Product Deep Dive</h1>
        <p class="text-sm text-zinc-500 font-mono">Real, sourced product data — every field is either scraped verbatim or explicitly blank, never guessed.</p>
    </div>

    @if($products->isEmpty())
        <div class="bg-surface-2 border border-border rounded-lg px-4 py-6 text-sm text-zinc-500">
            No products analyzed yet.
        </div>
    @else
        {{-- ── Cross-reference note: same silhouette + technique across brands ── --}}
        @php
            $groups = $products->groupBy(fn ($p) => strtolower(($p->metadata['silhouette_raw'] ?? '?') . '|' . ($p->metadata['graphic_technique'] ?? '?')))
                ->filter(fn ($g) => $g->pluck('brand.name')->unique()->count() > 1);
        @endphp
        @if($groups->isNotEmpty())
            <div class="bg-surface-2 border border-blue-900/40 rounded-lg px-4 py-3 mb-6 text-sm">
                <div class="text-xs text-blue-400 uppercase tracking-wide mb-1">Observed overlap</div>
                @foreach($groups as $group)
                    @php $brands = $group->pluck('brand.name')->unique()->filter(); @endphp
                    <p class="text-zinc-400">
                        <span class="text-zinc-200">{{ $brands->join(' and ') }}</span> both sell a
                        <span class="text-zinc-200">{{ $group->first()->metadata['silhouette_raw'] ?? '' }}</span> hoodie with
                        <span class="text-zinc-200">{{ $group->first()->metadata['graphic_technique'] ?? '' }}</span> graphics —
                        based only on matching attributes actually extracted from both pages, not a claim about who copied whom.
                    </p>
                @endforeach
            </div>
        @endif

        <div class="grid gap-4">
            @foreach($products as $product)
                @php $meta = $product->metadata ?? []; @endphp
                <div class="bg-surface-1 border border-border rounded-xl p-5">

                    <div class="flex flex-col md:flex-row gap-5">

                        {{-- Images --}}
                        @if(!empty($product->image_urls))
                            <div class="flex md:flex-col gap-2 flex-shrink-0 overflow-x-auto md:overflow-visible">
                                @foreach(array_slice($product->image_urls, 0, 3) as $img)
                                    <a href="{{ $img }}" target="_blank">
                                        <img src="{{ $img }}" loading="lazy"
                                             class="w-28 h-28 md:w-32 md:h-32 object-cover rounded-lg border border-border bg-surface-2 flex-shrink-0">
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div>
                                    <div class="text-xs text-zinc-500 uppercase tracking-wide">{{ $product->brand->name ?? 'Unknown brand' }}</div>
                                    <h2 class="text-lg font-medium text-zinc-100">{{ $product->name }}</h2>
                                    <a href="{{ $product->source_url }}" target="_blank" class="text-xs text-zinc-600 hover:text-zinc-400 break-all">{{ $product->source_url }}</a>
                                </div>
                                @if($product->price)
                                    <div class="text-right flex-shrink-0">
                                        <div class="font-mono text-xl text-emerald-400">{{ number_format($product->price, 0) }} {{ $product->currency }}</div>
                                        @if($product->availability)<div class="text-xs text-zinc-600">{{ $product->availability }}</div>@endif
                                    </div>
                                @endif
                            </div>

                            <div class="grid md:grid-cols-2 gap-4 text-sm">
                                <div class="space-y-2">
                                    <div>
                                        <span class="text-zinc-500">Silhouette:</span>
                                        <span class="text-zinc-200">{{ $meta['silhouette_raw'] ?? '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-zinc-500">Materials:</span>
                                        <span class="text-zinc-200">{{ !empty($meta['materials_raw']) ? implode(', ', $meta['materials_raw']) : '—' }}</span>
                                    </div>
                                    @if(!empty($meta['fabric_weight_stated']))
                                        <div>
                                            <span class="text-zinc-500">Fabric weight:</span>
                                            <span class="text-zinc-200">{{ $meta['fabric_weight_stated'] }}</span>
                                        </div>
                                    @endif
                                    <div>
                                        <span class="text-zinc-500">Construction:</span>
                                        @if(!empty($meta['construction_details']))
                                            <ul class="mt-1 space-y-0.5">
                                                @foreach($meta['construction_details'] as $detail)
                                                    <li class="text-zinc-300 text-xs">· {{ $detail }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-zinc-200">—</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <div>
                                        <span class="text-zinc-500">Graphic:</span>
                                        <span class="text-zinc-200">{{ $product->description ?: '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-zinc-500">Technique:</span>
                                        <span class="text-zinc-200">{{ $meta['graphic_technique'] ?? '—' }}</span>
                                        @if(!empty($meta['print_color_count']))
                                            <span class="text-zinc-500">({{ $meta['print_color_count'] }} color{{ $meta['print_color_count'] > 1 ? 's' : '' }})</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-zinc-500">Colors:</span>
                                        @forelse($product->colors as $color)
                                            <span class="px-2 py-0.5 rounded bg-surface-3 border border-border text-xs text-zinc-300">{{ $color->name }}</span>
                                        @empty
                                            <span class="text-zinc-200">—</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            @if(!empty($meta['expert_read']))
                                <div class="mt-4 pt-3 border-t border-border">
                                    <div class="text-xs text-purple-400 uppercase tracking-wide mb-1">Expert read</div>
                                    <p class="text-sm text-zinc-400 italic">{{ $meta['expert_read'] }}</p>
                                </div>
                            @endif
                        </div>

                    </div>

                </div>
            @endforeach
        </div>
    @endif

</div>
