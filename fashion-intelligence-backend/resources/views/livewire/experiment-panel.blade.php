<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-white">Experiments</h1>
        <p class="mt-1 text-sm text-zinc-500">Configure and run data collection pipelines.</p>
    </div>

    {{-- ── Experiment cards ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
        @foreach($experiments as $exp)
            @php
                $runs       = $runsByExperiment[$exp['key']] ?? collect();
                $lastRun    = $runs->first();
                $isRunning  = $runs->where('status', 'running')->isNotEmpty();
                $isSelected = $selectedKey === $exp['key'];
            @endphp

            <div wire:click="$set('selectedKey', '{{ $exp['key'] }}')"
                 class="bg-surface-1 border rounded-xl p-4 cursor-pointer transition-all
                        {{ $isSelected ? 'border-emerald-700/60 bg-emerald-950/20' : 'border-border hover:border-border-strong' }}">

                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-zinc-100">{{ $exp['name'] }}</span>
                            @if($isRunning)
                                <span class="flex items-center gap-1 text-xs text-amber-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse inline-block"></span>
                                    running
                                </span>
                            @endif
                        </div>
                        <div class="mt-1 text-xs text-zinc-500 font-mono">{{ $exp['key'] }}</div>
                    </div>

                    {{-- Last run status --}}
                    @if($lastRun)
                        <span class="text-xs px-2 py-0.5 rounded-full border
                            {{ $lastRun->status === 'completed' ? 'border-emerald-800/40 bg-emerald-950/30 text-emerald-400' :
                               ($lastRun->status === 'failed'   ? 'border-red-800/40 bg-red-950/30 text-red-400' :
                               ($lastRun->status === 'running'  ? 'border-amber-800/40 bg-amber-950/30 text-amber-400' :
                                                                   'border-border text-zinc-500')) }}">
                            {{ $lastRun->status }}
                        </span>
                    @endif
                </div>

                {{-- Config summary --}}
                <div class="mt-3 flex flex-wrap gap-3 text-xs text-zinc-500">
                    @if($exp['niche'])
                        <span class="flex items-center gap-1">
                            <span class="text-zinc-600">niche</span>
                            {{ $exp['niche'] }}
                        </span>
                    @endif
                    <span class="flex items-center gap-1">
                        <span class="text-zinc-600">countries</span>
                        {{ implode(', ', $exp['countries']) }}
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="text-zinc-600">keywords</span>
                        {{ $exp['keywords'] }}
                    </span>
                    @if($exp['subreddits'])
                        <span class="flex items-center gap-1">
                            <span class="text-zinc-600">subreddits</span>
                            {{ $exp['subreddits'] }}
                        </span>
                    @endif
                    <span class="flex items-center gap-1">
                        <span class="text-zinc-600">period</span>
                        {{ $exp['period_days'] }}d
                    </span>
                </div>

                {{-- Last run details --}}
                @if($lastRun)
                    <div class="mt-3 pt-3 border-t border-border flex flex-wrap gap-4 text-xs text-zinc-500">
                        <span>{{ $lastRun->created_at->diffForHumans() }}</span>
                        @if($lastRun->records_collected)
                            <span>{{ number_format($lastRun->records_collected) }} records</span>
                        @endif
                        @if($lastRun->duration_seconds)
                            <span>{{ gmdate('H:i:s', $lastRun->duration_seconds) }}</span>
                        @endif
                        @if($lastRun->actual_cost > 0)
                            <span>${{ number_format($lastRun->actual_cost, 4) }}</span>
                        @endif
                    </div>
                @endif

            </div>
        @endforeach
    </div>

    {{-- ── Launch controls ─────────────────────────────────────────────────── --}}
    @if($selectedKey)
        <div class="bg-surface-2 border border-border rounded-xl p-5 mb-8">
            <h2 class="text-sm font-medium text-zinc-300 mb-4">
                Launch: <span class="text-white font-mono">{{ $selectedKey }}</span>
            </h2>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    wire:click="runExperiment"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white text-sm font-medium
                           transition-colors disabled:opacity-50 flex items-center gap-2">
                    <svg wire:loading wire:target="runExperiment" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="runExperiment">▶ Run experiment</span>
                    <span wire:loading wire:target="runExperiment">Dispatching...</span>
                </button>

                <span class="text-xs text-zinc-600">
                    Dispatches to queue. Monitor with
                    <code class="px-1 py-0.5 bg-surface-3 rounded text-zinc-400">php artisan queue:work</code>
                </span>
            </div>

            @if($dispatching)
                <div class="mt-3 text-sm text-emerald-400 flex items-center gap-2">
                    <span>✓</span>
                    Experiment dispatched — check queue worker terminal for progress.
                </div>
            @endif
        </div>
    @endif

    {{-- ── Recent runs log ─────────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-3">Recent runs</h2>

        @if($runsByExperiment->isEmpty())
            <div class="bg-surface-1 border border-border rounded-xl px-4 py-12 text-center text-sm text-zinc-600">
                No runs yet. Select an experiment above and click Run.
            </div>
        @else
            <div class="bg-surface-1 border border-border rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-left px-4 py-2.5 text-xs text-zinc-500 font-medium">Experiment</th>
                            <th class="text-left px-4 py-2.5 text-xs text-zinc-500 font-medium">Provider</th>
                            <th class="text-left px-4 py-2.5 text-xs text-zinc-500 font-medium">Status</th>
                            <th class="text-right px-4 py-2.5 text-xs text-zinc-500 font-medium">Records</th>
                            <th class="text-right px-4 py-2.5 text-xs text-zinc-500 font-medium">Duration</th>
                            <th class="text-right px-4 py-2.5 text-xs text-zinc-500 font-medium">Cost</th>
                            <th class="text-right px-4 py-2.5 text-xs text-zinc-500 font-medium">Started</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($runsByExperiment->flatten()->sortByDesc('created_at')->take(20) as $run)
                            <tr class="border-b border-border last:border-0 hover:bg-surface-2">
                                <td class="px-4 py-2.5 font-mono text-xs text-zinc-400">
                                    {{ $run->experiment_name ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-zinc-500 text-xs">{{ $run->provider }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="text-xs px-1.5 py-0.5 rounded border
                                        {{ $run->status === 'completed' ? 'border-emerald-800/40 bg-emerald-950/30 text-emerald-400' :
                                           ($run->status === 'failed'   ? 'border-red-800/40 bg-red-950/30 text-red-400' :
                                           ($run->status === 'running'  ? 'border-amber-800/40 bg-amber-950/30 text-amber-400' :
                                                                           'border-border text-zinc-500')) }}">
                                        {{ $run->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-xs text-zinc-400">
                                    {{ $run->records_collected ? number_format($run->records_collected) : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-xs text-zinc-500">
                                    {{ $run->duration_seconds ? gmdate('H:i:s', $run->duration_seconds) : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-xs text-zinc-500">
                                    {{ $run->actual_cost > 0 ? '$' . number_format($run->actual_cost, 4) : 'free' }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-xs text-zinc-600">
                                    {{ $run->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
