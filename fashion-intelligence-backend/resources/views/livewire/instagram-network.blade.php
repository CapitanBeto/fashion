<div>
    <div class="mb-6 flex items-start justify-between">
        <div>
            <h1 class="text-xl font-semibold text-white">Instagram Network</h1>
            <p class="mt-1 text-sm text-zinc-500">
                Cuentas de alto interés descubiertas por búsqueda web. Aprueba las que quieras
                anotar manualmente — se escriben en <code class="text-zinc-400">cuentas_aprobadas.txt</code>
                en la raíz del proyecto.
            </p>
        </div>
        @if($pending->isNotEmpty())
            <button wire:click="approveAllPending"
                    wire:confirm="¿Aprobar las {{ $pending->count() }} cuentas pendientes?"
                    class="shrink-0 text-xs px-3 py-1.5 rounded bg-emerald-900/40 border border-emerald-800/50 text-emerald-400 hover:bg-emerald-900/60 transition-colors">
                Aprobar todas ({{ $pending->count() }})
            </button>
        @endif
    </div>

    {{-- ── Pending ──────────────────────────────────────────────────────────── --}}
    <div class="mb-8">
        <h2 class="text-sm font-medium text-zinc-300 mb-3">Pendientes ({{ $pending->count() }})</h2>

        @if($pending->isEmpty())
            <div class="text-sm text-zinc-600 bg-surface-1 border border-border rounded-xl p-4">
                No hay cuentas candidatas pendientes.
            </div>
        @else
            <div class="bg-surface-1 border border-border rounded-xl divide-y divide-border">
                @foreach($pending as $account)
                    <div class="p-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-zinc-100">{{ '@'.$account->username }}</span>
                                @if($account->discovered_via)
                                    <span class="text-xs text-zinc-600">vía {{ '@'.$account->discovered_via }}</span>
                                @endif
                            </div>
                            @if($account->reason)
                                <div class="text-xs text-zinc-500 mt-0.5">{{ $account->reason }}</div>
                            @endif
                            @if($account->source_note)
                                <div class="text-xs text-zinc-700 mt-0.5 truncate">{{ $account->source_note }}</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button wire:click="approve({{ $account->id }})"
                                    class="text-xs px-2.5 py-1 rounded bg-emerald-900/40 border border-emerald-800/50 text-emerald-400 hover:bg-emerald-900/60 transition-colors">
                                Aprobar
                            </button>
                            <button wire:click="reject({{ $account->id }})"
                                    class="text-xs px-2.5 py-1 rounded bg-surface-2 border border-border text-zinc-500 hover:text-zinc-300 transition-colors">
                                Descartar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Approved ─────────────────────────────────────────────────────────── --}}
    <div class="mb-8">
        <h2 class="text-sm font-medium text-zinc-300 mb-3">Aprobadas ({{ $approved->count() }})</h2>

        @if($approved->isEmpty())
            <div class="text-sm text-zinc-600 bg-surface-1 border border-border rounded-xl p-4">
                Ninguna aprobada todavía.
            </div>
        @else
            <div class="bg-surface-1 border border-border rounded-xl divide-y divide-border">
                @foreach($approved as $account)
                    <div class="p-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <span class="text-sm font-medium text-zinc-100">{{ '@'.$account->username }}</span>
                            @if($account->discovered_via)
                                <span class="text-xs text-zinc-600 ml-2">vía {{ '@'.$account->discovered_via }}</span>
                            @endif
                            @if($account->reason)
                                <div class="text-xs text-zinc-500 mt-0.5">{{ $account->reason }}</div>
                            @endif
                        </div>
                        <button wire:click="unapprove({{ $account->id }})"
                                class="text-xs px-2.5 py-1 rounded bg-surface-2 border border-border text-zinc-500 hover:text-zinc-300 transition-colors shrink-0">
                            Quitar
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Rejected (collapsed) ────────────────────────────────────────────── --}}
    @if($rejected->isNotEmpty())
        <details class="text-sm text-zinc-600">
            <summary class="cursor-pointer">Descartadas ({{ $rejected->count() }})</summary>
            <div class="mt-2 bg-surface-1 border border-border rounded-xl divide-y divide-border">
                @foreach($rejected as $account)
                    <div class="p-3 text-xs text-zinc-500">{{ '@'.$account->username }}</div>
                @endforeach
            </div>
        </details>
    @endif
</div>
