<x-layouts.public title="403 - Akses Ditolak">
    <div class="mx-auto max-w-2xl py-6 sm:py-12 text-center">
        <!-- 403 Graphic Badge -->
        <div class="relative inline-flex items-center justify-center">
            <div class="absolute inset-0 rounded-full bg-rose-500/20 blur-2xl transform scale-150 animate-pulse"></div>
            <div class="relative flex size-24 sm:size-32 items-center justify-center rounded-3xl bg-gradient-to-br from-navy via-navy-light to-navy shadow-xl border border-white/10 text-rose-500">
                <span class="font-mono text-4xl sm:text-5xl font-black tracking-wider">403</span>
            </div>
        </div>

        <h1 class="mt-8 text-2xl sm:text-4xl font-black text-navy tracking-tight">
            Akses Ditolak
        </h1>

        <p class="mt-3 text-sm sm:text-base text-slate-600 leading-relaxed max-w-lg mx-auto">
            Anda tidak memiliki hak akses atau izin yang cukup untuk membuka halaman atau melakukan tindakan ini.
        </p>

        <!-- Quick Action Cards -->
        <div class="mt-8 grid gap-4 sm:grid-cols-2 text-left">
            <a href="{{ url('/') }}" class="group card p-5 hover:border-orange/50 transition-all duration-200 hover:shadow-lg flex items-start gap-4">
                <div class="rounded-xl bg-orange/10 p-3 text-orange group-hover:bg-orange group-hover:text-white transition-colors shrink-0">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 001-1h2a1 1 0 001 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-black text-navy text-sm sm:text-base group-hover:text-orange transition-colors">Halaman Utama</h3>
                    <p class="text-xs text-muted mt-0.5">Kembali ke beranda portal konferensi Paperflow.</p>
                </div>
            </a>

            @auth
                <a href="{{ route('dashboard') }}" class="group card p-5 hover:border-navy/50 transition-all duration-200 hover:shadow-lg flex items-start gap-4">
                    <div class="rounded-xl bg-navy/10 p-3 text-navy group-hover:bg-navy group-hover:text-white transition-colors shrink-0">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-black text-navy text-sm sm:text-base group-hover:text-navy transition-colors">Dashboard Staff</h3>
                        <p class="text-xs text-muted mt-0.5">Kembali ke panel pengelola &amp; naskah ilmiah Anda.</p>
                    </div>
                </a>
            @else
                <a href="{{ route('login') }}" class="group card p-5 hover:border-navy/50 transition-all duration-200 hover:shadow-lg flex items-start gap-4">
                    <div class="rounded-xl bg-navy/10 p-3 text-navy group-hover:bg-navy group-hover:text-white transition-colors shrink-0">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-black text-navy text-sm sm:text-base group-hover:text-navy transition-colors">Login Staff</h3>
                        <p class="text-xs text-muted mt-0.5">Masuk ke sistem alur kerja editorial Paperflow.</p>
                    </div>
                </a>
            @endauth
        </div>

        <div class="mt-8 pt-6 border-t border-navy/10 flex items-center justify-center gap-4 text-xs font-semibold text-muted">
            <button onclick="history.back()" class="hover:text-navy transition inline-flex items-center gap-1 cursor-pointer">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali ke Halaman Sebelumnya</span>
            </button>
        </div>
    </div>
</x-layouts.public>
