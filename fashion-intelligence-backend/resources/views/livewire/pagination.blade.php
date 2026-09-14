<div class="flex items-center justify-between">
    <div class="text-xs text-zinc-600">
        @if ($paginator->firstItem())
            {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}
            of {{ number_format($paginator->total()) }}
        @endif
    </div>

    @if ($paginator->hasPages())
        <div class="flex items-center gap-1">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="px-2.5 py-1.5 rounded text-xs text-zinc-700 border border-border cursor-not-allowed">←</span>
            @else
                <button wire:click="previousPage" wire:loading.attr="disabled"
                        class="px-2.5 py-1.5 rounded text-xs text-zinc-400 border border-border
                               hover:border-border-strong hover:text-zinc-200 transition-colors">←</button>
            @endif

            {{-- Pages --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 py-1.5 text-xs text-zinc-600">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="px-2.5 py-1.5 rounded text-xs bg-surface-3 border border-border-strong text-zinc-200">
                                {{ $page }}
                            </span>
                        @else
                            <button wire:click="gotoPage({{ $page }})"
                                    class="px-2.5 py-1.5 rounded text-xs text-zinc-500 border border-border
                                           hover:border-border-strong hover:text-zinc-300 transition-colors">
                                {{ $page }}
                            </button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <button wire:click="nextPage" wire:loading.attr="disabled"
                        class="px-2.5 py-1.5 rounded text-xs text-zinc-400 border border-border
                               hover:border-border-strong hover:text-zinc-200 transition-colors">→</button>
            @else
                <span class="px-2.5 py-1.5 rounded text-xs text-zinc-700 border border-border cursor-not-allowed">→</span>
            @endif
        </div>
    @endif
</div>
