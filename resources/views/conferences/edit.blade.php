<x-layouts.app :title="'Edit Conference · '.$conference->name" heading="Conference Settings">
    <div class="max-w-4xl space-y-6">
        <x-conference-header :conference="$conference" active="settings" />
        <x-flash />
        <form method="POST" action="{{ route('conferences.update', $conference) }}" enctype="multipart/form-data" class="card mt-7 space-y-6 p-6 sm:p-8">
            @csrf
            @method('PUT')
            @include('conferences._form', ['conference' => $conference])
            <div class="flex items-center justify-end gap-3 pt-5 border-t border-navy/10">
                <a href="{{ route('conferences.show', $conference) }}" class="btn btn-secondary text-xs font-bold">Cancel</a>
                <button type="submit" class="btn btn-primary text-xs font-extrabold shadow-sm">Save Changes</button>
            </div>
        </form>

        @can('delete', $conference)
            <div class="card p-6 border border-rose-200 bg-rose-50/50 space-y-4" x-data="{ openDeleteModal: false, deleting: false }">
                <div>
                    <h3 class="font-extrabold text-rose-900 text-sm flex items-center gap-2">
                        <span>⚠️</span> Danger Zone — Delete Conference
                    </h3>
                    <p class="text-xs text-rose-700 mt-1 leading-relaxed">
                        Deleting this conference will permanently remove all configuration, assigned staff memberships, and all associated paper submissions. This action is restricted to Superadmin and cannot be undone.
                    </p>
                </div>
                <button type="button" @click="openDeleteModal = true" class="btn bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold py-2 px-4 shadow-2xs">
                    🗑️ Delete Conference Permanently
                </button>

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
                                Are you sure you want to permanently delete conference <strong class="text-navy font-black">&quot;{{ $conference->name }}&quot;</strong>? All associated submissions, checklist items, assigned staff, and settings will be permanently removed.
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
                                          window.location.href = '{{ route('conferences.index') }}';
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
            </div>
        @endcan
    </div>
</x-layouts.app>
