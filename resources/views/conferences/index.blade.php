<x-layouts.app title="Conference · Paperflow" heading="Conference">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Workspace</p><h1 class="page-title">Conference</h1><p class="page-subtitle">Kelola form dan alur editorial setiap conference.</p></div>@can('create', \App\Models\Conference::class)<a href="{{ route('conferences.create') }}" class="btn btn-primary">+ Conference baru</a>@endcan</div>
    <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($conferences as $conference)
            <a href="{{ route('conferences.show', $conference) }}" class="card group p-6 hover:border-orange/40 hover:shadow-lg"><div class="flex items-start justify-between"><span class="grid size-12 place-items-center rounded-xl bg-navy text-lg font-black text-white">{{ strtoupper(substr($conference->name, 0, 1)) }}</span><span class="badge badge-primary">{{ $conference->status->label() }}</span></div><h2 class="mt-5 text-lg font-extrabold text-navy group-hover:text-orange">{{ $conference->name }}</h2><p class="mt-1 text-sm text-muted">/{{ $conference->slug }}</p><div class="mt-5 flex items-center justify-between border-t border-navy/8 pt-4 text-sm"><span>{{ $conference->submissions_count }} paper</span><span class="font-bold text-orange">Buka →</span></div></a>
        @empty<div class="card col-span-full p-12 text-center text-muted">Belum ada conference pada workspace Anda.</div>@endforelse
    </div>
    <div class="mt-6">{{ $conferences->links() }}</div>
</x-layouts.app>
