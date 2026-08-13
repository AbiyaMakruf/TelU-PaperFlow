<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paperflow &middot; Editorial Workflow System</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col justify-between bg-warm text-ink antialiased" x-data="{ openSearch: false, search: '' }">
    <header class="border-b border-navy/10 bg-warm/90 backdrop-blur shrink-0">
        <div class="container-page flex h-20 items-center justify-between">
            <x-brand class="text-navy" />
            <div class="flex items-center gap-3">
                <a href="{{ route('login') }}" class="btn btn-secondary text-xs">Editorial Login</a>
            </div>
        </div>
    </header>
    <main class="flex-1 flex flex-col justify-center max-w-[1400px] mx-auto w-full">
        <section class="container-page grid items-center gap-12 py-12 lg:grid-cols-[1.1fr_.9fr]">
            <div>
                <p class="text-sm font-black uppercase tracking-[.24em] text-orange">Conference Editorial Workflow</p>
                <h1 class="mt-5 max-w-3xl text-5xl font-black leading-[1.05] text-navy sm:text-6xl">From submission to EDAS ready.</h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-muted">Paperflow unifies author submission forms, PIC assignments, editorial compliance checks, document versioning, and final review into a single streamlined workspace.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="button" @click="openSearch = true" class="btn btn-primary">Search Conferences 🔍</button>
                </div>
            </div>
            <div class="relative">
                <div class="absolute inset-10 rounded-full bg-orange/20 blur-3xl"></div>
                <div class="relative rounded-[2rem] bg-navy p-7 text-white shadow-2xl shadow-navy/25">
                    <div class="flex items-center justify-between"><p class="font-bold">Editorial Progress</p><span class="badge badge-warning">Live Workspace</span></div>
                    <div class="mt-8 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-white/8 p-5"><p class="text-sm text-white/55">Active Papers</p><p class="mt-2 text-3xl font-black">128</p></div>
                        <div class="rounded-2xl bg-orange p-5"><p class="text-sm text-white/75">Ready EDAS</p><p class="mt-2 text-3xl font-black">34</p></div>
                    </div>
                    <div class="mt-5 space-y-3">
                        @foreach ([['ICO-0214', 'Editorial review', '72%'], ['ICO-0215', 'Waiting author', '48%'], ['ICO-0216', 'Final reviewer', '91%']] as $item)
                            <div class="rounded-xl bg-white/8 p-4"><div class="flex justify-between text-sm"><span class="font-bold">{{ $item[0] }}</span><span class="text-white/60">{{ $item[1] }}</span></div><div class="mt-3 h-1.5 rounded-full bg-white/10"><div class="h-full rounded-full bg-orange" style="width: {{ $item[2] }}"></div></div></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- Interactive Conference Search Modal -->
        <div x-show="openSearch" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl" @click.away="openSearch = false">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-extrabold text-navy">Search Active Conferences</h3>
                    <button type="button" @click="openSearch = false" class="text-slate-400 hover:text-slate-700 font-bold text-2xl leading-none">&times;</button>
                </div>
                <p class="text-xs text-muted mt-1">Search for active conference names or descriptions accepting paper submissions.</p>
                <input class="form-input mt-4 py-3" x-model="search" placeholder="Type conference name..." autofocus>
                <div class="mt-4 max-h-96 overflow-y-auto space-y-3 pr-1">
                    @forelse ($conferences as $conference)
                        <a href="/{{ $conference->slug }}" x-show="!search || '{{ strtolower($conference->name) }}'.includes(search.toLowerCase())" class="card block p-4 hover:border-orange transition">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold text-navy">{{ $conference->name }}</h4>
                                <span class="text-xs font-bold text-orange">Open Landing Page &rarr;</span>
                            </div>
                            <p class="mt-1 text-xs text-muted line-clamp-2">{{ $conference->description ?: 'Paper submissions and editable manuscripts.' }}</p>
                        </a>
                    @empty
                        <p class="text-center py-6 text-sm text-muted">No active conferences currently accepting submissions.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </main>
    <footer class="bg-navy py-8 text-white/60 shrink-0"><div class="container-page flex flex-wrap justify-between gap-3 text-sm"><span>&copy; {{ date('Y') }} Paperflow</span><span>Editorial work, clearly moving.</span></div></footer>
</body>
</html>
