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
            <div class="card group p-6 hover:border-orange/40 hover:shadow-lg flex flex-col justify-between" x-data="{ openDeleteModal: false, deleting: false }">
                <div>
                    <div class="flex items-start justify-between">
                        <span class="grid size-12 place-items-center rounded-xl bg-navy text-lg font-black text-white">{{ strtoupper(substr($conference->name, 0, 1)) }}</span>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-primary">{{ $conference->status->label() }}</span>
                            @can('delete', $conference)
                                <button type="button" @click="openDeleteModal = true" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition text-xs font-bold" title="Delete Conference">
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

                @can('delete', $conference)
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
                                    Are you sure you want to permanently delete conference <strong class="text-navy font-black">{{ $conference->name }}</strong>? All associated submissions, checklist items, assigned staff, and settings will be permanently removed.
                                </p>

                                <form x-ref="deleteForm" method="POST" action="{{ url('/conferences/'.$conference->id.'/delete') }}" 
                                      @submit.prevent="
                                          deleting = true;
                                          fetch($el.action, {
                                              method: 'POST',
                                              headers: {
                                                  'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                  'Accept': 'application/json',
                                                  'X-Requested-With': 'XMLHttpRequest'
                                              },
                                              body: new FormData($refs.deleteForm)
                                          })
                                          .then(r => {
                                              if (!r.ok) {
                                                  return r.json().then(err => { throw new Error(err.message || 'Failed to delete conference.'); });
                                              }
                                              return r.json();
                                          })
                                          .then(data => {
                                              window.location.reload();
                                          })
                                          .catch(err => {
                                              alert(err.message || 'Error deleting conference.');
                                              deleting = false;
                                          });
                                      " 
                                      class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" @click="openDeleteModal = false" :disabled="deleting" class="btn btn-secondary text-xs font-bold">Cancel</button>
                                    <button type="submit" :disabled="deleting" class="btn bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold shadow-sm flex items-center gap-2">
                                        <span x-show="deleting" class="inline-block size-3 animate-spin rounded-full border-2 border-white border-t-transparent" x-cloak></span>
                                        <span x-text="deleting ? 'Deleting...' : 'Yes, Delete Conference'"></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </template>
                @endcan
            </div>
        @empty
            <div class="card col-span-full p-12 text-center text-muted">No conferences found in your workspace.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $conferences->links() }}</div>
</x-layouts.app>
