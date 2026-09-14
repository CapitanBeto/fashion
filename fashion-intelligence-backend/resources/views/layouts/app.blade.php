<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Fashion Intelligence' }}</title>

    {{-- Tailwind CDN for MVP — replace with compiled CSS in production --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
                    },
                    colors: {
                        surface: {
                            0: '#0a0a0b',
                            1: '#111113',
                            2: '#18181b',
                            3: '#222226',
                            4: '#2c2c32',
                        },
                        border: '#2e2e34',
                        'border-strong': '#3f3f47',
                    },
                }
            }
        }
    </script>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @livewireStyles

    <style>
        body { background-color: #0a0a0b; color: #e4e4e7; }

        /* Lifecycle stage pills */
        .stage-EMERGING       { background: #0d3320; color: #4ade80; border-color: #166534; }
        .stage-EARLY_ADOPTION { background: #0f3d2e; color: #34d399; border-color: #065f46; }
        .stage-ACCELERATING   { background: #1a3a12; color: #86efac; border-color: #14532d; }
        .stage-MAINSTREAM     { background: #1e3a1e; color: #bbf7d0; border-color: #166534; }
        .stage-PEAK           { background: #2d2200; color: #fde047; border-color: #713f12; }
        .stage-SATURATED      { background: #2d1f00; color: #fb923c; border-color: #7c2d12; }
        .stage-DECLINING      { background: #2d0f0f; color: #f87171; border-color: #7f1d1d; }
        .stage-DEAD           { background: #1c1c1e; color: #71717a; border-color: #3f3f46; }

        /* Score bar */
        .score-bar-fill { transition: width 0.4s ease; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: #111113; }
        ::-webkit-scrollbar-thumb { background: #3f3f47; border-radius: 2px; }
    </style>
</head>
<body class="h-full antialiased">

    <div class="min-h-full flex flex-col">

        {{-- Top nav --}}
        <header class="border-b border-border bg-surface-1 sticky top-0 z-40">
            <div class="max-w-screen-2xl mx-auto px-4 flex items-center justify-between h-12">

                {{-- Wordmark --}}
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                    <div class="w-5 h-5 rounded bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center">
                        <div class="w-2 h-2 rounded-sm bg-emerald-400"></div>
                    </div>
                    <span class="text-sm font-medium text-zinc-100 tracking-tight">Fashion Intelligence</span>
                </a>

                {{-- Nav links --}}
                <nav class="hidden md:flex items-center gap-1">
                    @foreach([
                        ['route' => 'dashboard',    'label' => 'Fashion Radar — Chile'],
                        ['route' => 'all-trends',   'label' => 'All Trends'],
                        ['route' => 'products',     'label' => 'Products'],
                        ['route' => 'opportunities','label' => 'Opportunities'],
                        ['route' => 'experiments',  'label' => 'Experiments'],
                        ['route' => 'sources',      'label' => 'Sources'],
                    ] as $link)
                        <a href="{{ route($link['route']) }}"
                           class="px-3 py-1.5 rounded text-sm transition-colors
                                  {{ request()->routeIs($link['route'])
                                     ? 'bg-surface-3 text-zinc-100'
                                     : 'text-zinc-400 hover:text-zinc-200 hover:bg-surface-2' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>

                {{-- Right side --}}
                <div class="flex items-center gap-3">
                    {{-- Engine status dot --}}
                    <div id="engine-status" class="flex items-center gap-1.5 text-xs text-zinc-500">
                        <div class="w-1.5 h-1.5 rounded-full bg-zinc-600"></div>
                        engine
                    </div>

                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-xs text-zinc-500 hover:text-zinc-300 transition-colors">
                                Sign out
                            </button>
                        </form>
                    @endauth
                </div>

            </div>
        </header>

        {{-- Main --}}
        <main class="flex-1 max-w-screen-2xl w-full mx-auto px-4 py-6">
            {{ $slot }}
        </main>

    </div>

    @livewireScripts

    {{-- Engine status ping --}}
    <script>
        async function checkEngine() {
            try {
                const r = await fetch('/api/v1/system/health', { signal: AbortSignal.timeout(3000) });
                const dot = document.getElementById('engine-status');
                if (r.ok) {
                    dot.innerHTML = '<div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div><span class="text-emerald-400">engine</span>';
                } else {
                    dot.innerHTML = '<div class="w-1.5 h-1.5 rounded-full bg-amber-400"></div><span class="text-amber-400">engine</span>';
                }
            } catch {}
        }
        checkEngine();
        setInterval(checkEngine, 30000);
    </script>
</body>
</html>
