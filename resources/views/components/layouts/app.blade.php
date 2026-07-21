@props(['title' => 'Paperflow', 'heading' => 'Dashboard'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Paperflow' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-warm text-ink antialiased">
    @php
        $userConferences = auth()->user()->isSuperAdmin()
            ? \App\Models\Conference::orderBy('name')->get()
            : \App\Models\Conference::whereIn('id', auth()->user()->conferenceMemberships()->where('is_active', true)->pluck('conference_id'))->orderBy('name')->get();
        $activeConf = session('active_conference_id') ? $userConferences->firstWhere('id', session('active_conference_id')) : null;
    @endphp

    @if(session('impersonated_by'))
        <div class="bg-amber-500 text-slate-950 px-4 py-2 text-sm font-bold flex items-center justify-between z-50 sticky top-0">
            <span>⚠️ Mode Impersonation Aktif: Masuk sebagai {{ auth()->user()->name }} ({{ auth()->user()->email }}).</span>
            <form method="POST" action="{{ route('impersonate.leave') }}" class="inline">
                @csrf
                <button class="bg-slate-900 text-white text-xs px-3 py-1 rounded-lg hover:bg-slate-800 font-extrabold">Keluar Impersonation &rarr;</button>
            </form>
        </div>
    @endif
    <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]" x-data="{ mobileMenu: false }" x-on:keydown.escape.window="mobileMenu = false">
        <div x-cloak x-show="mobileMenu" x-transition.opacity class="fixed inset-0 z-40 bg-navy/55 backdrop-blur-sm lg:hidden" x-on:click="mobileMenu = false"></div>
        <aside x-cloak x-show="mobileMenu" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 z-50 flex w-[min(84vw,320px)] flex-col overflow-y-auto bg-navy px-5 py-6 text-white shadow-2xl lg:hidden">
            <div class="flex items-center justify-between"><x-brand class="px-2 text-white" /><button type="button" class="grid size-11 place-items-center rounded-xl bg-white/10 text-xl" x-on:click="mobileMenu = false" aria-label="Tutup menu">&times;</button></div>
            <div class="mt-6 border-y border-white/10 py-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-white/50 block mb-1">Active Workspace</span>
                <form method="POST" action="{{ route('workspace.switch') }}">
                    @csrf
                    <select name="conference_id" onchange="this.form.submit()" class="w-full rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-xs font-bold text-white cursor-pointer">
                        <option value="all" class="text-slate-900" @selected(!$activeConf)>🌐 Semua Conference</option>
                        @foreach($userConferences as $conf)
                            <option value="{{ $conf->id }}" class="text-slate-900" @selected($activeConf?->id === $conf->id)>📌 {{ $conf->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <nav class="mt-4 space-y-2 text-sm font-semibold">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">Dashboard</a>
                <a href="{{ route('submissions.index') }}" class="nav-link {{ request()->routeIs('submissions.*') ? 'nav-link-active' : '' }}">Paper</a>
                <a href="{{ route('conferences.index') }}" class="nav-link {{ request()->routeIs('conferences.*') ? 'nav-link-active' : '' }}">Conference</a>
                <a href="{{ route('editor-performance.index') }}" class="nav-link {{ request()->routeIs('editor-performance.*') ? 'nav-link-active' : '' }}">Performa editor</a>
                <a href="{{ route('audit.index') }}" class="nav-link {{ request()->routeIs('audit.*') ? 'nav-link-active' : '' }}">Audit log</a>
                @if(auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}">Pengguna</a>
                    <a href="{{ route('admin.monitoring.index') }}" class="nav-link {{ request()->routeIs('admin.monitoring.*') ? 'nav-link-active' : '' }}">Monitoring</a>
                @endif
            </nav>
            <div class="mt-auto border-t border-white/10 pt-5"><p class="truncate font-bold">{{ auth()->user()->name }}</p><p class="mt-1 truncate text-xs text-white/55">{{ auth()->user()->email }}</p><form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn w-full border border-white/15 text-white hover:border-orange hover:text-orange">Keluar</button></form></div>
        </aside>
        <aside class="hidden bg-navy px-5 py-7 text-white lg:flex lg:flex-col">
            <x-brand class="px-2 text-white" />
            <nav class="mt-10 space-y-2 text-sm font-semibold">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}"><span class="text-xs">DB</span><span>Dashboard</span></a>
                <a href="{{ route('submissions.index') }}" class="nav-link {{ request()->routeIs('submissions.*') ? 'nav-link-active' : '' }}"><span class="text-xs">PF</span><span>Paper</span></a>
                <a href="{{ route('conferences.index') }}" class="nav-link {{ request()->routeIs('conferences.*') ? 'nav-link-active' : '' }}"><span class="text-xs">CF</span><span>Conference</span></a>
                <a href="{{ route('editor-performance.index') }}" class="nav-link {{ request()->routeIs('editor-performance.*') ? 'nav-link-active' : '' }}"><span class="text-xs">ST</span><span>Performa editor</span></a>
                <a href="{{ route('audit.index') }}" class="nav-link {{ request()->routeIs('audit.*') ? 'nav-link-active' : '' }}"><span class="text-xs">AU</span><span>Audit log</span></a>
                @if(auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}"><span class="text-xs">US</span><span>Pengguna</span></a>
                    <a href="{{ route('admin.monitoring.index') }}" class="nav-link {{ request()->routeIs('admin.monitoring.*') ? 'nav-link-active' : '' }}"><span class="text-xs">MO</span><span>Monitoring</span></a>
                @endif
            </nav>
            <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="truncate font-bold">{{ auth()->user()->name }}</p>
                <p class="mt-1 truncate text-xs text-white/55">{{ auth()->user()->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="text-xs font-bold text-orange hover:text-white">Keluar &rarr;</button></form>
            </div>
        </aside>
        <div class="min-w-0">
            <header class="sticky top-0 z-30 flex min-h-16 items-center justify-between gap-2 border-b border-navy/10 bg-white/90 px-4 py-3 backdrop-blur sm:px-8 lg:px-10">
                <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl bg-navy text-xl text-white lg:hidden" x-on:click="mobileMenu = true" aria-label="Buka menu"><span class="-mt-1">☰</span></button>
                <x-brand class="min-w-0 scale-90 text-navy sm:scale-100 lg:hidden" />
                <div class="hidden lg:block"><p class="text-xs font-bold uppercase tracking-[.18em] text-muted">Paperflow workspace</p><p class="font-bold text-navy">{{ $heading ?? 'Dashboard' }}</p></div>
                <!-- GCP-style Workspace Selector in Header -->
                <div class="ml-auto flex items-center gap-2 sm:gap-4">
                    <form method="POST" action="{{ route('workspace.switch') }}" class="flex items-center gap-1.5">
                        @csrf
                        <span class="text-[11px] font-black uppercase tracking-wider text-slate-400 hidden sm:inline">Workspace:</span>
                        <select name="conference_id" onchange="this.form.submit()" class="rounded-xl border border-navy/20 bg-slate-100 px-3 py-1.5 text-xs font-extrabold text-navy hover:bg-slate-200 transition focus:ring-2 focus:ring-orange cursor-pointer">
                            <option value="all" @selected(!$activeConf)>🌐 Semua Conference</option>
                            @foreach($userConferences as $conf)
                                <option value="{{ $conf->id }}" @selected($activeConf?->id === $conf->id)>📌 {{ $conf->name }}</option>
                            @endforeach
                        </select>
                    </form>
                    <a href="{{ route('notifications.index') }}" class="relative grid size-10 place-items-center rounded-full bg-navy/5 font-bold text-navy" aria-label="Notifikasi">N @if(auth()->user()->unreadNotifications()->exists())<span class="absolute right-0 top-0 size-2.5 rounded-full bg-orange"></span>@endif</a>
                    @if(auth()->user()->isSuperAdmin())<span class="badge badge-warning hidden sm:inline-flex">Superadmin</span>@endif
                    <span class="hidden size-10 place-items-center rounded-full bg-navy font-bold text-white sm:grid">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
            </header>
            <main class="min-w-0 p-4 sm:p-8 lg:p-10"><x-flash />{{ $slot }}</main>
        </div>
    </div>
</body>
</html>
