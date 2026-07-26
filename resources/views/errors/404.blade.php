<x-layouts.public title="404 - Page Not Found">
    <div class="mx-auto max-w-xl py-8 sm:py-16 text-center">
        <!-- 404 Graphic Badge -->
        <div class="relative inline-flex items-center justify-center">
            <div class="absolute inset-0 rounded-full bg-orange/20 blur-2xl transform scale-150 animate-pulse"></div>
            <div class="relative flex size-24 sm:size-32 items-center justify-center rounded-3xl bg-gradient-to-br from-navy via-navy-light to-navy shadow-xl border border-white/10 text-orange">
                <span class="font-mono text-4xl sm:text-5xl font-black tracking-wider">404</span>
            </div>
        </div>

        <h1 class="mt-8 text-2xl sm:text-4xl font-black text-navy tracking-tight">
            Page Not Found
        </h1>

        <p class="mt-3 text-sm sm:text-base text-slate-600 leading-relaxed max-w-md mx-auto">
            Sorry, the page or academic document you are looking for could not be found, may have been moved, or the link is no longer valid.
        </p>

        <div class="mt-8 pt-6 border-t border-navy/10 flex items-center justify-center gap-4 text-xs font-semibold text-muted">
            <button onclick="history.back()" class="btn btn-secondary text-xs px-4 py-2 inline-flex items-center gap-1.5 cursor-pointer">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Go Back to Previous Page</span>
            </button>
        </div>
    </div>
</x-layouts.public>
