<x-layouts.app :title="$conference->name.' · Paperflow'" :heading="$conference->name">
    <x-conference-header :conference="$conference" active="overview" />

    @if($conference->isGoogleFormMode())
        @can('update', $conference)
            <div class="mt-6 rounded-2xl border border-purple-200 bg-purple-50/60 p-6 shadow-xs space-y-4" x-data="csvImportModal()">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-purple-200/70 pb-4">
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-purple-300 bg-purple-100 px-3 py-1 text-xs font-black text-purple-900">
                            📥 Smart CSV / Excel Import Active
                        </span>
                        <h2 class="mt-2 text-lg font-black text-navy">Google Form / Spreadsheet Import</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="openModal = true" class="btn btn-primary text-xs font-bold shrink-0 shadow-xs">
                            📥 Import CSV / Excel File
                        </button>
                        <a href="{{ route('conferences.edit', $conference) }}" class="btn btn-secondary text-xs font-bold shrink-0">
                            ⚙️ Edit Column Mapping
                        </a>
                    </div>
                </div>
                
                <p class="text-xs text-slate-700 leading-relaxed">
                    This conference is configured for Google Form / Spreadsheet CSV Import. You can export submissions from Google Form as a CSV or Excel file and import them directly into <strong>{{ $conference->name }}</strong> anytime. Paperflow automatically matches column headers and updates existing submissions safely without duplicate entries.
                </p>

                <!-- Modal Portal -->
                <template x-teleport="body">
                    <div x-show="openModal" class="fixed inset-0 z-50 overflow-y-auto bg-navy/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
                        <div @click.away="resetModal()" class="card w-full max-w-2xl bg-white p-6 shadow-2xl rounded-2xl space-y-5 border border-navy/10 relative">
                            <div class="flex items-center justify-between border-b border-navy/10 pb-3">
                                <h3 class="text-base font-extrabold text-navy flex items-center gap-2">
                                    <span>📥</span> Import Submissions into {{ $conference->name }}
                                </h3>
                                <button type="button" @click="resetModal()" class="text-muted hover:text-navy text-lg font-bold">✕</button>
                            </div>

                            <div x-show="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 font-bold" x-cloak x-text="errorMessage"></div>

                            <!-- Step 1: Upload File -->
                            <div x-show="step === 1" class="space-y-4">
                                <p class="text-xs text-muted leading-relaxed">
                                    Upload your Google Form or Google Sheets export file (.csv). Paperflow will automatically detect header columns and update submissions specifically for <strong>{{ $conference->name }}</strong>.
                                </p>
                                <label class="border-2 border-dashed border-navy/20 hover:border-orange bg-slate-50/50 hover:bg-orange/5 rounded-2xl p-8 flex flex-col items-center justify-center text-center cursor-pointer transition">
                                    <span class="text-3xl mb-2">📄</span>
                                    <strong class="text-navy text-sm font-bold">Choose a CSV file or drag it here</strong>
                                    <small class="text-muted text-xs mt-1">Supports .csv, .tsv, or .txt files up to 20MB</small>
                                    <input type="file" accept=".csv,.tsv,.txt" @change="handleFileSelect($event)" class="hidden">
                                </label>
                                <div x-show="loading" class="flex items-center justify-center gap-2 text-xs font-bold text-orange py-2">
                                    <span class="animate-spin">⏳</span> Reading CSV header columns...
                                </div>
                            </div>

                            <!-- Step 2: Auto-Detected Header Column Mapping Confirmation -->
                            <div x-show="step === 2" class="space-y-4">
                                <div class="flex items-center justify-between bg-orange/10 p-3 rounded-xl border border-orange/20">
                                    <span class="text-xs font-bold text-navy">Headers Detected Successfully!</span>
                                    <span class="text-xs font-black text-orange" x-text="`${totalRows} data rows found`"></span>
                                </div>

                                <p class="text-xs text-muted">Confirm or adjust how CSV columns map to Paperflow fields for {{ $conference->name }}:</p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <label class="form-label text-xs font-bold">Paper ID Column <span class="text-rose-500">*</span></label>
                                        <select class="form-input text-xs" x-model="mapping.paper_id_column">
                                            <template x-for="h in headers" :key="h">
                                                <option :value="h" x-text="h" :selected="h === mapping.paper_id_column"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs font-bold">Paper Title Column <span class="text-rose-500">*</span></label>
                                        <select class="form-input text-xs" x-model="mapping.title_column">
                                            <template x-for="h in headers" :key="h">
                                                <option :value="h" x-text="h" :selected="h === mapping.title_column"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs font-bold">Author Name Column <span class="text-rose-500">*</span></label>
                                        <select class="form-input text-xs" x-model="mapping.author_name_column">
                                            <template x-for="h in headers" :key="h">
                                                <option :value="h" x-text="h" :selected="h === mapping.author_name_column"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs font-bold">Author Email Column <span class="text-rose-500">*</span></label>
                                        <select class="form-input text-xs" x-model="mapping.author_email_column">
                                            <template x-for="h in headers" :key="h">
                                                <option :value="h" x-text="h" :selected="h === mapping.author_email_column"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs font-bold">Author Phone Column</label>
                                        <select class="form-input text-xs" x-model="mapping.author_phone_column">
                                            <option value="">(None)</option>
                                            <template x-for="h in headers" :key="h">
                                                <option :value="h" x-text="h" :selected="h === mapping.author_phone_column"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs font-bold">Manuscript File URL Column</label>
                                        <select class="form-input text-xs" x-model="mapping.manuscript_file_column">
                                            <option value="">(None)</option>
                                            <template x-for="h in headers" :key="h">
                                                <option :value="h" x-text="h" :selected="h === mapping.manuscript_file_column"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-3 pt-3 border-t border-navy/10">
                                    <button type="button" @click="step = 1" class="btn btn-secondary text-xs">Back</button>
                                    <button type="button" @click="processImport()" :disabled="loading" class="btn btn-primary text-xs font-bold inline-flex items-center gap-1.5">
                                        <span x-show="loading" class="animate-spin">⏳</span>
                                        <span x-text="loading ? 'Processing Import...' : 'Confirm & Process Import'"></span
                                    </button>
                                </div>
                            </div>

                            <!-- Step 3: Success Summary Statistics -->
                            <div x-show="step === 3" class="space-y-4 text-center py-4">
                                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 text-2xl font-black mx-auto">✓</div>
                                <h4 class="text-base font-extrabold text-navy">CSV Import Completed Successfully!</h4>
                                
                                <div class="grid grid-cols-3 gap-3 text-xs mt-3">
                                    <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200">
                                        <span class="block font-black text-lg text-emerald-700" x-text="successStats?.new || 0"></span>
                                        <span class="text-muted font-bold text-[11px]">New Papers</span>
                                    </div>
                                    <div class="p-3 bg-purple-50 rounded-xl border border-purple-200">
                                        <span class="block font-black text-lg text-purple-700" x-text="successStats?.updated || 0"></span>
                                        <span class="text-muted font-bold text-[11px]">Updated (v2/v3)</span>
                                    </div>
                                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                        <span class="block font-black text-lg text-slate-700" x-text="successStats?.skipped || 0"></span>
                                        <span class="text-muted font-bold text-[11px]">Unchanged/Skipped</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-navy/10">
                                    <button type="button" @click="window.location.reload()" class="btn btn-primary text-xs font-bold w-full">Done & Refresh Overview</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        @endcan
    @endif

    <div class="mt-6 grid gap-4 grid-cols-2 xl:grid-cols-4">
        <div class="card p-5 border border-navy/10">
            <p class="text-xs font-extrabold uppercase tracking-wider text-muted">Total Papers</p>
            <p class="mt-2 text-3xl font-black text-navy">{{ $conference->submissions_count }}</p>
        </div>
        <div class="card p-5 border border-navy/10">
            <p class="text-xs font-extrabold uppercase tracking-wider text-muted">New Submissions</p>
            <p class="mt-2 text-3xl font-black text-navy">{{ $statusCounts['submitted'] ?? 0 }}</p>
        </div>
        <div class="card p-5 border border-navy/10">
            <p class="text-xs font-extrabold uppercase tracking-wider text-muted">Editorial Review</p>
            <p class="mt-2 text-3xl font-black text-navy">{{ $statusCounts['editorial_review'] ?? 0 }}</p>
        </div>
        <div class="card p-5 border border-navy/10">
            <p class="text-xs font-extrabold uppercase tracking-wider text-muted">Completed (Done)</p>
            <p class="mt-2 text-3xl font-black text-emerald-600">{{ $statusCounts['done'] ?? 0 }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 grid-cols-1 xl:grid-cols-[1.2fr_.8fr]">
        <section class="card p-6">
            <h2 class="font-black text-navy text-lg border-b border-navy/8 pb-3">Conference Configuration</h2>
            <dl class="mt-5 grid gap-4 text-xs sm:text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted font-medium text-xs">Timezone</dt>
                    <dd class="mt-1 font-extrabold text-navy">{{ $conference->timezone }}</dd>
                </div>
                <div>
                    <dt class="text-muted font-medium text-xs">Submission Period</dt>
                    <dd class="mt-1 font-extrabold text-navy">
                        {{ $conference->submission_opens_at?->format('d M Y H:i') ?? 'No start limit' }} &ndash; {{ $conference->submission_closes_at?->format('d M Y H:i') ?? 'No end limit' }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-muted font-medium text-xs">Description</dt>
                    <dd class="mt-1 leading-relaxed text-slate-700 break-words">{{ $conference->description ?: 'No description provided.' }}</dd>
                </div>
            </dl>
        </section>

        <section class="card p-6">
            <div class="flex items-center justify-between border-b border-navy/8 pb-3">
                <h2 class="font-black text-navy text-lg">Active Staff Team</h2>
                @can('manageMembers', $conference)
                    <a href="{{ route('conferences.members.index', $conference) }}" class="text-xs font-extrabold text-orange hover:underline">Manage Team &rarr;</a>
                @endcan
            </div>
            <div class="mt-4 space-y-3">
                @foreach($conference->memberships->where('is_active', true)->take(6) as $membership)
                    <div class="flex items-center gap-3 p-2 rounded-xl bg-warm/60">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-navy text-sm font-bold text-white shadow-sm">
                            {{ strtoupper(substr($membership->user->name, 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold text-navy">{{ $membership->user->name }}</p>
                            <p class="text-[11px] text-muted">{{ $membership->role->label() }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    @can('update', $conference)
        <details class="card mt-6 p-6">
            <summary class="cursor-pointer font-black text-navy text-base select-none">Duplicate Conference</summary>
            <p class="mt-2 text-xs text-muted">Copy submission form, IEEE checklists, and email templates to a new conference.</p>
            <form method="POST" action="{{ route('conferences.duplicate', $conference) }}" class="mt-5 grid gap-4 grid-cols-1 sm:grid-cols-[1fr_220px_auto]">
                @csrf
                <input class="form-input text-xs" name="name" placeholder="New conference name" required>
                <input class="form-input text-xs" name="slug" placeholder="new-slug" required>
                <button class="btn btn-secondary text-xs font-extrabold">Duplicate Conference</button>
            </form>
        </details>
    @endcan

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('csvImportModal', () => ({
        openModal: false,
        step: 1,
        loading: false,
        tempFileId: '',
        headers: [],
        totalRows: 0,
        mapping: {
            paper_id_column: '',
            title_column: '',
            author_name_column: '',
            author_email_column: '',
            author_phone_column: '',
            manuscript_file_column: ''
        },
        errorMessage: '',
        successStats: null,

        handleFileSelect(e) {
            const files = e.target.files || e.dataTransfer.files;
            if (!files || !files.length) return;
            this.uploadAndPreview(files[0]);
        },

        async uploadAndPreview(file) {
            this.loading = true;
            this.errorMessage = '';
            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', '{{ csrf_token() }}');

            try {
                const res = await fetch('{{ Route::has("conferences.import.preview") ? route("conferences.import.preview", $conference) : url("/conferences/".$conference->id."/import/preview") }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Failed to preview CSV file.');
                }
                this.tempFileId = data.temp_file_id;
                this.headers = data.headers || [];
                this.totalRows = data.total_rows || 0;
                this.mapping = data.detected_mapping || {};
                this.step = 2;
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },

        async processImport() {
            this.loading = true;
            this.errorMessage = '';
            try {
                const res = await fetch('{{ Route::has("conferences.import.process") ? route("conferences.import.process", $conference) : url("/conferences/".$conference->id."/import/process") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        temp_file_id: this.tempFileId,
                        mapping: this.mapping
                    })
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Failed to process CSV import.');
                }
                this.successStats = data.stats;
                this.step = 3;
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },

        resetModal() {
            this.openModal = false;
            this.step = 1;
            this.loading = false;
            this.tempFileId = '';
            this.headers = [];
            this.errorMessage = '';
            this.successStats = null;
        }
    }));
});
</script>
</x-layouts.app>
