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
    <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3" x-data="{ openDeleteModal: false, deleteUrl: '', deleteName: '' }">
        @forelse($conferences as $conference)
            <div class="card group p-6 hover:border-orange/40 hover:shadow-lg flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between">
                        <span class="grid size-12 place-items-center rounded-xl bg-navy text-lg font-black text-white">{{ strtoupper(substr($conference->name, 0, 1)) }}</span>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-primary">{{ $conference->status->label() }}</span>
                            @can('delete', $conference)
                                <button type="button" @click="deleteUrl = '{{ url('/conferences/'.$conference->id.'/delete') }}'; deleteName = '{{ e($conference->name) }}'; openDeleteModal = true" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition text-xs font-bold" title="Delete Conference">
                                    🗑️
                                </button>
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

        <!-- Custom Alpine.js Delete Confirmation Modal -->
        <template x-teleport="body">
            <div x-show="openDeleteModal" class="fixed inset-0 z-50 overflow-y-auto bg-navy/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
                <div @click.away="openDeleteModal = false" class="card w-full max-w-md bg-white p-6 shadow-2xl rounded-2xl space-y-5 border border-rose-200 relative animate-in fade-in zoom-in duration-150">
                    <div class="flex items-center gap-3 text-rose-600">
                        <span class="grid size-10 place-items-center rounded-xl bg-rose-100 text-rose-700 text-lg font-black shrink-0">⚠️</span>
                        <div>
                            <h3 class="text-base font-extrabold text-navy">Delete Conference Permanently?</h3>
                            <p class="text-xs text-rose-700 font-bold">This action cannot be undone.</p>
                        </div>
                    </div>
                    
                    <p class="text-xs text-slate-700 leading-relaxed">
                        Are you sure you want to permanently delete conference <strong class="text-navy font-black" x-text="deleteName"></strong>? All associated submissions, checklist items, assigned staff, and settings will be permanently removed.
                    </p>

                    <form method="POST" :action="deleteUrl" class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        @csrf
                        @method('DELETE')
                        <button type="button" @click="openDeleteModal = false" class="btn btn-secondary text-xs font-bold">Cancel</button>
                        <button type="submit" class="btn bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold shadow-sm">
                            Yes, Delete Conference
                        </button>
                    </form>
                </div>
            </div>
        </template>
    </div>
    <div class="mt-6">{{ $conferences->links() }}</div>
</x-layouts.app>
