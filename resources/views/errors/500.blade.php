<x-layouts.public title="500 - Internal Server Error">
    <div class="mx-auto max-w-xl py-8 sm:py-16 text-center">
        <!-- 500 Graphic Badge -->
        <div class="relative inline-flex items-center justify-center">
            <div class="absolute inset-0 rounded-full bg-amber-500/20 blur-2xl transform scale-150 animate-pulse"></div>
            <div class="relative flex size-24 sm:size-32 items-center justify-center rounded-3xl bg-gradient-to-br from-navy via-navy-light to-navy shadow-xl border border-white/10 text-amber-500">
                <span class="font-mono text-4xl sm:text-5xl font-black tracking-wider">500</span>
            </div>
        </div>

        <h1 class="mt-8 text-2xl sm:text-4xl font-black text-navy tracking-tight">
            Internal Server Error
        </h1>

        <p class="mt-3 text-sm sm:text-base text-slate-600 leading-relaxed max-w-md mx-auto">
            Sorry, an unexpected internal error occurred while processing your request. Please try again later.
        </p>

        <div class="mt-8 pt-6 border-t border-navy/10 flex items-center justify-center gap-4 text-xs font-semibold text-muted">
            <button onclick="window.location.reload()" class="btn btn-secondary text-xs px-4 py-2 inline-flex items-center gap-1.5 cursor-pointer">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Reload Page</span>
            </button>
        </div>
    </div>
</x-layouts.public>
