<x-layouts.app title="Conferences · Paperflow" heading="Conferences">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Workspace</p>
            <h1 class="page-title">Conferences</h1>
            <p class="page-subtitle">Manage submission forms and editorial workflows for each conference.</p>
        </div>
        @can('create', \App\Models\Conference::class)
            <a href="{{ route('conferences.create') }}" class="btn btn-primary">+ New Conference</a>
        @endcan
    </div>
    <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($conferences as $conference)
            <div class="card group p-6 hover:border-orange/40 hover:shadow-lg flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between">
                        <span class="grid size-12 place-items-center rounded-xl bg-navy text-lg font-black text-white">{{ strtoupper(substr($conference->name, 0, 1)) }}</span>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-primary">{{ $conference->status->label() }}</span>
                            @can('delete', $conference)
                                <form method="POST" action="{{ route('conferences.destroy', $conference) }}" onsubmit="return confirm('Are you sure you want to PERMANENTLY DELETE conference &quot;{{ $conference->name }}&quot;?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition text-xs font-bold" title="Delete Conference">
                                        🗑️
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    <a href="{{ route('conferences.show', $conference) }}" class="block">
                        <h2 class="mt-5 text-lg font-extrabold text-navy group-hover:text-orange">{{ $conference->name }}</h2>
                        <p class="mt-1 text-sm text-muted">/{{ $conference->slug }}</p>
                    </a>
                </div>
                <div class="mt-5 flex items-center justify-between border-t border-navy/8 pt-4 text-sm">
                    <span>{{ $conference->submissions_count }} {{ Str::plural('paper', $conference->submissions_count) }}</span>
                    <a href="{{ route('conferences.show', $conference) }}" class="font-bold text-orange hover:underline">Open &rarr;</a>
                </div>
            </div>
        @empty
            <div class="card col-span-full p-12 text-center text-muted">No conferences found in your workspace.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $conferences->links() }}</div>
</x-layouts.app>
