<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — Fashion Intelligence</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; background: #0a0a0b; color: #e4e4e7; } </style>
</head>
<body class="h-full flex items-center justify-center">

    <div class="w-full max-w-sm px-4">

        {{-- Logo --}}
        <div class="flex items-center justify-center gap-2.5 mb-8">
            <div class="w-6 h-6 rounded bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center">
                <div class="w-2.5 h-2.5 rounded-sm bg-emerald-400"></div>
            </div>
            <span class="text-sm font-medium text-zinc-100 tracking-tight">Fashion Intelligence</span>
        </div>

        {{-- Card --}}
        <div class="bg-[#111113] border border-[#2e2e34] rounded-2xl p-6">
            <h1 class="text-base font-medium text-zinc-100 mb-5">Sign in</h1>

            @if($errors->any())
                <div class="mb-4 px-3 py-2 rounded-lg bg-red-950/40 border border-red-800/40 text-sm text-red-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-3">
                @csrf

                <div>
                    <label class="block text-xs text-zinc-500 mb-1.5" for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        class="w-full bg-[#18181b] border border-[#2e2e34] rounded-lg px-3 py-2 text-sm text-zinc-200
                               placeholder-zinc-600 focus:outline-none focus:border-[#3f3f47] transition-colors"
                        placeholder="admin@fashion-intelligence.local"
                    >
                </div>

                <div>
                    <label class="block text-xs text-zinc-500 mb-1.5" for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full bg-[#18181b] border border-[#2e2e34] rounded-lg px-3 py-2 text-sm text-zinc-200
                               placeholder-zinc-600 focus:outline-none focus:border-[#3f3f47] transition-colors"
                        placeholder="••••••••"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full mt-1 px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-600
                           text-white text-sm font-medium transition-colors">
                    Sign in
                </button>

            </form>
        </div>

        <p class="mt-4 text-center text-xs text-zinc-600">
            Default: <code class="text-zinc-500">admin@fashion-intelligence.local</code>
        </p>

    </div>

</body>
</html>
