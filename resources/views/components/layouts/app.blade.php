@props(['title' => 'Paperflow', 'heading' => 'Dashboard'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Paperflow' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-warm text-ink antialiased">
    @php
        $userConferences = auth()->check()
            ? (auth()->user()->isSuperAdmin()
                ? \App\Models\Conference::orderBy('name')->get()
                : \App\Models\Conference::whereIn('id', auth()->user()->conferenceMemberships()->where('is_active', true)->pluck('conference_id'))->orderBy('name')->get())
            : collect();
        $activeConf = session('active_conference_id') ? $userConferences->firstWhere('id', session('active_conference_id')) : null;

        if (auth()->check()) {
            $authUser = auth()->user();
            if ($authUser->isSuperAdmin()) {
                $userRoleLabel = 'Superadmin';
                $userRoleBadgeClass = 'bg-amber-100 text-amber-900 border border-amber-300';
            } else {
                $currentMembership = $activeConf
                    ? $authUser->conferenceMemberships()->where('conference_id', $activeConf->id)->where('is_active', true)->first()
                    : $authUser->conferenceMemberships()->where('is_active', true)->first();
                $userRoleLabel = $currentMembership?->role?->label() ?? 'Staff';
                $userRoleBadgeClass = match($currentMembership?->role) {
                    \App\Enums\ConferenceRole::Admin => 'bg-indigo-100 text-indigo-900 border border-indigo-300',
                    \App\Enums\ConferenceRole::Editorial => 'bg-sky-100 text-sky-900 border border-sky-300',
                    \App\Enums\ConferenceRole::Reviewer => 'bg-emerald-100 text-emerald-900 border border-emerald-300',
                    default => 'bg-slate-100 text-slate-800 border border-slate-300',
                };
            }
        }
    @endphp

    @if(session('impersonated_by'))
        <div class="bg-amber-500 text-slate-950 px-4 py-2 text-sm font-bold flex items-center justify-between z-50 sticky top-0">
            <span>⚠️ Active Impersonation Mode: Logged in as {{ auth()->user()->name }} ({{ auth()->user()->email }}).</span>
            <form method="POST" action="{{ route('impersonate.leave') }}" class="inline">
                @csrf
                <button class="bg-slate-900 text-white text-xs px-3 py-1 rounded-lg hover:bg-slate-800 font-extrabold">Exit Impersonation &rarr;</button>
            </form>
        </div>
    @endif
    <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr] max-w-full overflow-x-hidden" x-data="{ mobileMenu: false }" x-on:keydown.escape.window="mobileMenu = false">
        <div x-cloak x-show="mobileMenu" x-transition.opacity class="fixed inset-0 z-40 bg-navy/55 backdrop-blur-sm lg:hidden" x-on:click="mobileMenu = false"></div>
        <aside x-cloak x-show="mobileMenu" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 z-50 flex w-[min(84vw,320px)] flex-col overflow-y-auto bg-navy px-5 py-6 text-white shadow-2xl lg:hidden">
            <div class="flex items-center justify-between"><x-brand class="px-2 text-white" /><button type="button" class="grid size-11 place-items-center rounded-xl bg-white/10 text-xl" x-on:click="mobileMenu = false" aria-label="Close menu">&times;</button></div>
            <div x-data="{ openWsMobile: false }" @click.away="openWsMobile = false" class="mt-6 border-y border-white/10 py-3 relative">
                <span class="text-[10px] font-black uppercase tracking-wider text-white/50 block mb-1.5">Active Workspace</span>
                <button type="button" @click="openWsMobile = !openWsMobile" class="w-full flex items-center justify-between rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-xs font-bold text-white hover:bg-white/15 transition cursor-pointer">
                    <span class="flex items-center gap-2 truncate">
                        <span class="size-2 rounded-full bg-orange shrink-0"></span>
                        <span class="truncate">{{ $activeConf ? '📌 ' . $activeConf->name : '🌐 All Conferences' }}</span>
                    </span>
                    <svg class="size-3.5 text-white/70 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openWsMobile }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="openWsMobile" x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="mt-2 w-full rounded-xl bg-slate-900 border border-white/15 p-1.5 shadow-2xl text-white divide-y divide-white/10 z-50">
                    <div class="py-1 space-y-1 max-h-56 overflow-y-auto">
                        <form method="POST" action="{{ route('workspace.switch') }}">
                            @csrf
                            <input type="hidden" name="conference_id" value="all">
                            <button type="submit" class="w-full text-left flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-bold transition {{ !$activeConf ? 'bg-orange text-white font-black' : 'text-slate-200 hover:bg-white/10' }}">
                                <span class="truncate">🌐 All Conferences</span>
                                @if(!$activeConf)
                                    <span class="text-xs font-black">✓</span>
                                @endif
                            </button>
                        </form>

                        @foreach($userConferences as $conf)
                            <form method="POST" action="{{ route('workspace.switch') }}">
                                @csrf
                                <input type="hidden" name="conference_id" value="{{ $conf->id }}">
                                <button type="submit" class="w-full text-left flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-bold transition {{ $activeConf?->id === $conf->id ? 'bg-orange text-white font-black' : 'text-slate-200 hover:bg-white/10' }}">
                                    <span class="truncate" title="{{ $conf->name }}">📌 {{ $conf->name }}</span>
                                    @if($activeConf?->id === $conf->id)
                                        <span class="text-xs font-black">✓</span>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>

            <nav class="mt-4 space-y-2 text-sm font-semibold">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">Dashboard</a>
                <a href="{{ route('submissions.index') }}" class="nav-link {{ request()->routeIs('submissions.*') ? 'nav-link-active' : '' }}">Papers</a>
                <a href="{{ route('conferences.index') }}" class="nav-link {{ request()->routeIs('conferences.*') ? 'nav-link-active' : '' }}">Conferences</a>
                <a href="{{ route('editor-performance.index') }}" class="nav-link {{ request()->routeIs('editor-performance.*') ? 'nav-link-active' : '' }}">Editor Performance</a>
                @auth
                    @if(auth()->user()->isSuperAdmin() || auth()->user()->conferenceMemberships()->where('is_active',true)->where('role',\App\Enums\ConferenceRole::Admin)->exists())
                        <a href="{{ route('emails.index') }}" class="nav-link {{ request()->routeIs('emails.*') ? 'nav-link-active' : '' }}">Email Monitoring</a>
                    @endif
                    @if(auth()->user()->isSuperAdmin() || auth()->user()->conferenceMemberships()->where('is_active',true)->where('role',\App\Enums\ConferenceRole::Admin)->exists())
                        <a href="{{ route('admin.monitoring.index') }}" class="nav-link {{ request()->routeIs('admin.monitoring.*') || request()->routeIs('audit.*') ? 'nav-link-active' : '' }}">Monitoring &amp; Audit</a>
                    @endif
                    @if(auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}">Users</a>
                    @endif
                    <a href="{{ route('user-manual.index') }}" class="nav-link {{ request()->routeIs('user-manual.*') ? 'nav-link-active' : '' }}">User Manual</a>
                @else
                    <a href="{{ route('user-manual.author') }}" class="nav-link {{ request()->routeIs('user-manual.*') ? 'nav-link-active' : '' }}">User Manual</a>
                @endauth
            </nav>
            @auth
                <div class="mt-auto border-t border-white/10 pt-5">
                    <a href="{{ route('profile.edit') }}" class="block group cursor-pointer" title="Manage My Profile">
                        <p class="truncate font-bold group-hover:text-orange transition">{{ auth()->user()->name }}</p>
                        <p class="mt-1 truncate text-xs text-white/55 group-hover:text-white/85 transition">{{ '@' . auth()->user()->username }} &middot; {{ auth()->user()->email }}</p>
                    </a>
                    <div class="grid grid-cols-2 gap-2 mt-4">
                        <a href="{{ route('profile.edit') }}" class="btn border border-white/15 text-white hover:bg-white/10 text-xs px-2 py-2 text-center">Edit Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn w-full border border-white/15 text-white hover:border-orange hover:text-orange text-xs px-2 py-2">Logout</button>
                        </form>
                    </div>
                </div>
            @endauth
        </aside>
        <aside class="hidden bg-navy px-5 py-7 text-white lg:flex lg:flex-col">
            <x-brand class="px-2 text-white" />

            <!-- Active Workspace Selector (Desktop Sidebar) -->
            <div x-data="{ openWsDesktop: false }" @click.away="openWsDesktop = false" class="mt-6 border-y border-white/10 py-3 relative">
                <span class="text-[10px] font-black uppercase tracking-wider text-white/50 block mb-1.5">Active Workspace</span>
                <button type="button" @click="openWsDesktop = !openWsDesktop" class="w-full flex items-center justify-between rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-xs font-bold text-white hover:bg-white/15 transition cursor-pointer">
                    <span class="flex items-center gap-2 truncate">
                        <span class="size-2 rounded-full bg-orange shrink-0"></span>
                        <span class="truncate">{{ $activeConf ? '📌 ' . $activeConf->name : '🌐 All Conferences' }}</span>
                    </span>
                    <svg class="size-3.5 text-white/70 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openWsDesktop }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown Menu Popover -->
                <div x-show="openWsDesktop" x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute left-0 mt-2 w-full rounded-xl bg-slate-900 border border-white/15 p-1.5 shadow-2xl text-white divide-y divide-white/10 z-50">
                    <div class="py-1 space-y-1 max-h-56 overflow-y-auto">
                        <form method="POST" action="{{ route('workspace.switch') }}">
                            @csrf
                            <input type="hidden" name="conference_id" value="all">
                            <button type="submit" class="w-full text-left flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-bold transition {{ !$activeConf ? 'bg-orange text-white font-black' : 'text-slate-200 hover:bg-white/10' }}">
                                <span class="truncate">🌐 All Conferences</span>
                                @if(!$activeConf)
                                    <span class="text-xs font-black">✓</span>
                                @endif
                            </button>
                        </form>
                        @foreach($userConferences as $conf)
                            <form method="POST" action="{{ route('workspace.switch') }}">
                                @csrf
                                <input type="hidden" name="conference_id" value="{{ $conf->id }}">
                                <button type="submit" class="w-full text-left flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-bold transition {{ $activeConf?->id === $conf->id ? 'bg-orange text-white font-black' : 'text-slate-200 hover:bg-white/10' }}">
                                    <span class="truncate" title="{{ $conf->name }}">📌 {{ $conf->name }}</span>
                                    @if($activeConf?->id === $conf->id)
                                        <span class="text-xs font-black">✓</span>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>

            <nav class="mt-4 space-y-2 text-sm font-semibold">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}"><span class="text-xs">DB</span><span>Dashboard</span></a>
                <a href="{{ route('submissions.index') }}" class="nav-link {{ request()->routeIs('submissions.*') ? 'nav-link-active' : '' }}"><span class="text-xs">PF</span><span>Papers</span></a>
                <a href="{{ route('conferences.index') }}" class="nav-link {{ request()->routeIs('conferences.*') ? 'nav-link-active' : '' }}"><span class="text-xs">CF</span><span>Conferences</span></a>
                <a href="{{ route('editor-performance.index') }}" class="nav-link {{ request()->routeIs('editor-performance.*') ? 'nav-link-active' : '' }}"><span class="text-xs">ST</span><span>Editor Performance</span></a>
                @auth
                    @if(auth()->user()->isSuperAdmin() || auth()->user()->conferenceMemberships()->where('is_active',true)->where('role',\App\Enums\ConferenceRole::Admin)->exists())
                        <a href="{{ route('emails.index') }}" class="nav-link {{ request()->routeIs('emails.*') ? 'nav-link-active' : '' }}"><span class="text-xs">EM</span><span>Email Monitoring</span></a>
                    @endif
                    @if(auth()->user()->isSuperAdmin() || auth()->user()->conferenceMemberships()->where('is_active',true)->where('role',\App\Enums\ConferenceRole::Admin)->exists())
                        <a href="{{ route('admin.monitoring.index') }}" class="nav-link {{ request()->routeIs('admin.monitoring.*') || request()->routeIs('audit.*') ? 'nav-link-active' : '' }}"><span class="text-xs">MO</span><span>Monitoring &amp; Audit</span></a>
                    @endif
                    @if(auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}"><span class="text-xs">US</span><span>Users</span></a>
                    @endif
                    <a href="{{ route('user-manual.index') }}" class="nav-link {{ request()->routeIs('user-manual.*') ? 'nav-link-active' : '' }}"><span class="text-xs">UM</span><span>User Manual</span></a>
                @else
                    <a href="{{ route('user-manual.author') }}" class="nav-link {{ request()->routeIs('user-manual.*') ? 'nav-link-active' : '' }}"><span class="text-xs">UM</span><span>User Manual</span></a>
                @endauth
            </nav>
            @auth
                <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4">
                    <a href="{{ route('profile.edit') }}" class="block group cursor-pointer" title="Manage My Profile">
                        <p class="truncate font-bold group-hover:text-orange transition">{{ auth()->user()->name }}</p>
                        <p class="mt-1 truncate text-xs text-white/55 group-hover:text-white/85 transition">{{ '@' . auth()->user()->username }} &middot; {{ auth()->user()->email }}</p>
                    </a>
                    <div class="mt-4 pt-3 border-t border-white/10 flex items-center justify-between text-xs">
                        <a href="{{ route('profile.edit') }}" class="font-bold text-white/70 hover:text-white transition">Edit Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="font-bold text-orange hover:text-white transition">Logout &rarr;</button>
                        </form>
                    </div>
                </div>
            @endauth
        </aside>
        <div class="min-w-0 w-full max-w-full overflow-x-hidden">
            <header class="sticky top-0 z-30 flex min-h-14 sm:min-h-16 items-center justify-between gap-2 border-b border-navy/10 bg-white/95 px-3 sm:px-8 lg:px-10 py-2.5 backdrop-blur">
                <div class="flex items-center gap-2 min-w-0 shrink-0">
                    <button type="button" class="grid size-9 sm:size-10 shrink-0 place-items-center rounded-xl bg-navy text-lg text-white lg:hidden" x-on:click="mobileMenu = true" aria-label="Open menu"><span class="-mt-0.5">☰</span></button>
                </div>
                <div class="hidden lg:block"><p class="text-xs font-bold uppercase tracking-[.18em] text-muted truncate max-w-xs sm:max-w-md">{{ $activeConf ? '📌 Workspace: ' . $activeConf->name : '🌐 Workspace: All Conferences' }}</p><p class="font-bold text-navy">{{ $heading ?? 'Dashboard' }}</p></div>
                <!-- Profile Icon & Notifications in Header -->
                <div class="ml-auto flex items-center gap-1.5 sm:gap-4 shrink-0">
                    @auth
                        <!-- Notification Button with Bell Icon & Label -->
                        <a href="{{ route('notifications.index') }}" class="relative inline-flex items-center justify-center gap-1.5 rounded-xl border border-navy/20 bg-slate-100 p-2 sm:px-3 sm:py-1.5 text-xs font-extrabold text-navy hover:bg-slate-200 transition focus:ring-2 focus:ring-orange shrink-0" title="Notifications">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 text-navy">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <span class="hidden sm:inline">Notifications</span>
                            @if(auth()->user()->unreadNotifications()->exists())
                                <span class="absolute -top-0.5 -right-0.5 sm:static size-2 rounded-full bg-orange animate-pulse"></span>
                            @endif
                        </a>
                        <!-- Profile Header Link with Role Badge & Username -->
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-1.5 sm:gap-2 rounded-xl p-1 hover:bg-slate-100 transition group shrink-0" title="Manage My Profile ({{ auth()->user()->name }})">
                            <div class="text-right flex flex-col items-end leading-tight min-w-0">
                                <span class="inline-flex items-center rounded-md px-1.5 sm:px-2 py-0.5 text-[9px] sm:text-[10px] font-black tracking-tight {{ $userRoleBadgeClass }}">
                                    {{ $userRoleLabel }}
                                </span>
                                <span class="text-[10px] sm:text-[11px] font-semibold text-slate-500 mt-0.5 group-hover:text-orange transition truncate max-w-[80px] xs:max-w-[110px] sm:max-w-[140px]">
                                    {{ '@' . auth()->user()->username }}
                                </span>
                            </div>
                            <span class="grid size-8 sm:size-10 place-items-center rounded-full bg-navy text-xs sm:text-sm font-black text-white shadow-sm group-hover:bg-orange transition shrink-0">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary text-xs">Staff Login &rarr;</a>
                    @endauth
                </div>
            </header>
            <main class="min-w-0 w-full max-w-[1600px] mx-auto px-3 py-4 sm:px-8 sm:py-8 lg:px-10 lg:py-10 space-y-6 overflow-x-hidden"><x-toast-notification /><x-flash />{{ $slot }}</main>
        </div>
    </div>
</body>
</html>
