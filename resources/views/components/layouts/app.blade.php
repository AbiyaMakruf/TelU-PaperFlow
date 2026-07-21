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
<body class="min-h-screen bg-warm text-ink antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
        <aside class="hidden bg-navy px-5 py-7 text-white lg:flex lg:flex-col">
            <x-brand class="px-2 text-white" />
            <nav class="mt-10 space-y-2 text-sm font-semibold">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}"><span class="text-xs">DB</span><span>Dashboard</span></a>
                <a href="{{ route('submissions.index') }}" class="nav-link {{ request()->routeIs('submissions.*') ? 'nav-link-active' : '' }}"><span class="text-xs">PF</span><span>Paper</span></a>
                <a href="{{ route('conferences.index') }}" class="nav-link {{ request()->routeIs('conferences.*') ? 'nav-link-active' : '' }}"><span class="text-xs">CF</span><span>Conference</span></a>
                @if(auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}"><span class="text-xs">US</span><span>Pengguna</span></a>
                @endif
            </nav>
            <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="truncate font-bold">{{ auth()->user()->name }}</p>
                <p class="mt-1 truncate text-xs text-white/55">{{ auth()->user()->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="text-xs font-bold text-orange hover:text-white">Keluar &rarr;</button></form>
            </div>
        </aside>
        <div class="min-w-0">
            <header class="flex min-h-18 items-center justify-between gap-4 border-b border-navy/10 bg-white/80 px-5 py-3 backdrop-blur sm:px-8 lg:px-10">
                <x-brand class="text-navy lg:hidden" />
                <div class="hidden lg:block"><p class="text-xs font-bold uppercase tracking-[.18em] text-muted">Paperflow workspace</p><p class="font-bold text-navy">{{ $heading ?? 'Dashboard' }}</p></div>
                <nav class="ml-auto flex gap-1 lg:hidden"><a class="btn btn-ghost px-3" href="{{ route('dashboard') }}">Dashboard</a><a class="btn btn-ghost px-3" href="{{ route('submissions.index') }}">Paper</a></nav>
                <div class="flex items-center gap-3">@if(auth()->user()->isSuperAdmin())<span class="badge badge-warning hidden sm:inline-flex">Superadmin</span>@endif<span class="grid size-10 place-items-center rounded-full bg-navy font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span></div>
            </header>
            <main class="p-5 sm:p-8 lg:p-10"><x-flash />{{ $slot }}</main>
        </div>
    </div>
</body>
</html>
