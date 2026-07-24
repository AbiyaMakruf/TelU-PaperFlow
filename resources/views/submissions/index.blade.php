<x-layouts.app title="Papers · Paperflow" heading="Papers">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="eyebrow">Editorial Pipeline</p>
            <h1 class="page-title">All Papers</h1>
            <p class="page-subtitle">Monitor submissions, PIC assignments, and review stages in a single unified table.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <div x-data="{ open: false }" class="relative w-full sm:w-auto">
                <button type="button" @click="open = !open" class="btn btn-secondary w-full sm:w-auto">
                    Export Report ▾
                </button>
                <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-48 rounded-xl bg-white p-2 shadow-xl border border-slate-200 z-50">
                    <a class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg" href="{{ route('submissions.export', array_merge(request()->query(), ['format' => 'csv'])) }}">CSV File (.csv)</a>
                    <a class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg" href="{{ route('submissions.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}">Microsoft Excel (.xlsx)</a>
                    <a class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg" target="_blank" href="{{ route('submissions.export', array_merge(request()->query(), ['format' => 'pdf'])) }}">PDF Report (Print)</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form Container -->
    <div x-data="{ mobileFilterOpen: {{ request()->query() ? 'true' : 'false' }} }" class="card mt-7 p-4 sm:p-5">
        <button type="button" @click="mobileFilterOpen = !mobileFilterOpen" class="flex w-full items-center justify-between font-bold text-navy md:hidden">
            <span class="flex items-center gap-2 text-sm">
                🔍 Filter &amp; Search
                @if(request()->query())
                    <span class="badge badge-primary text-[10px]">Active</span>
                @endif
            </span>
            <span class="text-xs text-orange" x-text="mobileFilterOpen ? 'Close −' : 'Open +'"></span>
        </button>

        <form x-show="mobileFilterOpen || window.innerWidth >= 768" x-collapse class="mt-4 md:mt-0 space-y-4">
            <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="form-label">Search</label>
                    <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Search code, title, author...">
                </div>
                <div>
                    <label class="form-label">Conference</label>
                    <select class="form-input" name="conference">
                        <option value="">All conferences</option>
                        @foreach($conferences as $conference)
                            <option value="{{ $conference->id }}" @selected(request('conference') === $conference->id)>{{ $conference->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Paper Status</label>
                    <select class="form-input" name="status">
                        <option value="">All statuses</option>
                        @foreach(\App\Enums\SubmissionStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Editor PIC</label>
                    <select class="form-input" name="editor">
                        <option value="">All editors</option>
                        @foreach($staff as $person)
                            <option value="{{ $person->id }}" @selected((string)request('editor') === (string)$person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Reviewer PIC</label>
                    <select class="form-input" name="reviewer">
                        <option value="">All reviewers</option>
                        @foreach($staff as $person)
                            <option value="{{ $person->id }}" @selected((string)request('reviewer') === (string)$person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Submitted From</label>
                    <input class="form-input" type="date" name="date_from" value="{{ request('date_from') }}">
                </div>
                <div>
                    <label class="form-label">Submitted To</label>
                    <input class="form-input" type="date" name="date_to" value="{{ request('date_to') }}">
                </div>
                <div>
                    <label class="form-label">Additional Filters</label>
                    <label class="check-row h-12 py-0 px-4 flex items-center cursor-pointer">
                        <input type="checkbox" name="overdue" value="1" @checked(request('overdue'))>
                        <span class="text-xs font-extrabold text-navy">Overdue Only</span>
                    </label>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-navy/8">
                <button class="btn btn-primary px-6 w-full sm:w-auto">Apply Filter</button>
                @if(request()->query())
                    <a class="btn btn-ghost w-full sm:w-auto text-center" href="{{ route('submissions.index') }}">Reset Filter</a>
                @endif
            </div>
        </form>
    </div>

    <div x-data="{ selected: [], bulkAction: '', activePic: null, staffMap: {{ json_encode($staffData) }} }">
        <!-- Floating Bulk Bar -->
        <div x-show="selected.length > 0" x-cloak class="sticky top-4 z-40 my-4 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-slate-900 p-4 text-white shadow-2xl">
            <div class="text-sm font-bold">
                <span x-text="selected.length"></span> papers selected
            </div>
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <button type="button" @click="$dispatch('open-bulk-assign')" class="btn btn-primary text-xs py-1.5 px-3 w-full sm:w-auto">
                    Bulk Assign PIC &amp; Deadline
                </button>
                <button type="button" @click="$dispatch('open-bulk-status')" class="btn btn-secondary text-xs py-1.5 px-3 w-full sm:w-auto">
                    Bulk Update Status
                </button>
                <form method="POST" action="{{ route('submissions.bulk-download') }}" class="inline-block w-full sm:w-auto">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="submission_ids[]" :value="id">
                    </template>
                    <button type="submit" class="btn text-xs py-1.5 px-3 w-full sm:w-auto bg-amber-500 hover:bg-amber-600 text-white font-extrabold border-0 shadow-sm transition">
                        📦 Bulk Download Author Files (ZIP)
                    </button>
                </form>
                <button type="button" @click="selected = []" class="text-xs text-slate-400 hover:text-white underline">
                    Deselect All
                </button>
            </div>
        </div>

        @php
            $sortUrl = fn (string $column) => route('submissions.index', array_merge(request()->query(), [
                'sort' => $column,
                'direction' => request('sort') === $column && request('direction', 'desc') === 'asc' ? 'desc' : 'asc',
            ]));
        @endphp

        <div class="card mt-6 overflow-hidden">
            <!-- Desktop Table View -->
            <div class="hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10">
                                <input type="checkbox" @change="selected = $event.target.checked ? [{{ $submissions->pluck('id')->map(fn($id) => "'$id'")->join(',') }}] : []">
                            </th>
                            <th><a href="{{ $sortUrl('paper_id') }}">Paper ID ↕</a></th>
                            <th><a href="{{ $sortUrl('title') }}">Title ↕</a></th>
                            <th>Format</th>
                            <th><a href="{{ $sortUrl('pic') }}">PIC ↕</a></th>
                            <th><a href="{{ $sortUrl('status') }}">Status ↕</a></th>
                            <th><a href="{{ $sortUrl('submitted_at') }}">Submitted ↕</a></th>
                            <th></th>
                        </tr>
                    </thead>
                    @forelse ($submissions as $submission)
                        <tbody x-data="{ open: false }" class="border-b border-navy/8">
                        <tr>
                            <td>
                                <input type="checkbox" value="{{ $submission->id }}" x-model="selected">
                            </td>
                            <td>
                                <a href="{{ route('submissions.show', $submission) }}" class="font-black text-navy hover:text-orange hover:underline block">
                                    {{ $submission->paper_id ?: $submission->paper_code }}
                                </a>
                                <p class="mt-1 text-xs text-muted">{{ $submission->conference->name }}</p>
                                @if($submission->is_flagged_duplicate)
                                    <span class="mt-1 inline-block rounded bg-rose-100 px-2 py-0.5 text-[10px] font-extrabold text-rose-700" title="{{ $submission->duplicate_notes }}">⚠️ Potential Duplicate</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('submissions.show', $submission) }}" class="block max-w-lg font-bold text-navy hover:text-orange hover:underline" title="{{ $submission->title }}">
                                    {{ $submission->title }}
                                </a>
                            </td>
                            <td>
                                @if($submission->manuscript_format === 'latex')
                                    <span class="badge badge-warning text-[11px] font-black">LaTeX (ZIP)</span>
                                @elseif($submission->manuscript_format === 'docx')
                                    <span class="badge badge-info text-[11px] font-black">DOCX (Word)</span>
                                @else
                                    <span class="badge badge-neutral text-[11px] text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($submission->editor)
                                    <button type="button" @click="activePic = staffMap[{{ $submission->editor->id }}]" class="font-bold text-navy hover:text-orange hover:underline text-left block">
                                        👤 {{ $submission->editor->name }}
                                    </button>
                                @else
                                    <p class="text-muted text-xs">No editor assigned</p>
                                @endif
                                @if($submission->reviewer)
                                    <button type="button" @click="activePic = staffMap[{{ $submission->reviewer->id }}]" class="mt-1 text-xs text-muted hover:text-orange hover:underline text-left block">
                                        🔍 Reviewer: {{ $submission->reviewer->name }}
                                    </button>
                                @else
                                    <p class="mt-1 text-xs text-muted">Reviewer: Unassigned</p>
                                @endif
                            </td>
                            <td><x-status-badge :status="$submission->status" /></td>
                            <td>{{ $submission->submitted_at?->format('d M Y') ?? '-' }}@if($submission->deadline_at)<p class="mt-1 text-xs {{ $submission->isOverdue() ? 'text-danger font-bold':'text-muted' }}">Deadline {{ $submission->deadline_at->format('d M Y') }}</p>@endif</td>
                            <td><button type="button" class="font-bold text-orange" x-on:click="open = !open" x-text="open ? 'Close −' : 'View +'">View +</button></td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="8" class="bg-warm/70 p-5">
                                <div class="grid gap-4 text-sm md:grid-cols-4">
                                    <div><p class="form-label">Internal Code</p><p class="font-bold text-navy">{{ $submission->paper_code }}</p></div>
                                    <div><p class="form-label">Primary Author</p><p class="font-bold text-navy">{{ $submission->corresponding_author_name }}</p><p class="text-muted">{{ $submission->corresponding_author_email }}</p></div>
                                    <div><p class="form-label">Editable Format</p><p class="font-bold text-navy">{{ $submission->manuscript_format === 'latex' ? 'LaTeX (ZIP)' : ($submission->manuscript_format === 'docx' ? 'Microsoft Word (DOCX)' : 'Not confirmed by admin') }}</p></div>
                                    <div><p class="form-label">Author Count</p><p class="font-bold text-navy">{{ $submission->authors->count() }}</p></div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-3"><a href="{{ route('submissions.show', $submission) }}" class="btn btn-secondary px-4 py-2 text-xs">Open full details</a>@if($submission->files->first())<span class="self-center text-xs text-muted">File: {{ $submission->files->first()->original_name }}</span>@endif</div>
                            </td>
                        </tr>
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="8" class="py-12 text-center text-muted">No papers match the selected filters.</td></tr></tbody>
                    @endforelse
                </table>
            </div>

            <!-- Mobile Card List View -->
            <div class="divide-y divide-navy/10 md:hidden">
                @forelse($submissions as $submission)
                    <article x-data="{ open: false }" class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-2 min-w-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <input type="checkbox" value="{{ $submission->id }}" x-model="selected" class="shrink-0">
                                <a href="{{ route('submissions.show', $submission) }}" class="truncate font-black text-navy hover:text-orange text-base">
                                    {{ $submission->paper_id ?: $submission->paper_code }}
                                </a>
                            </div>
                            <div class="shrink-0">
                                @if($submission->manuscript_format === 'latex')
                                    <span class="badge badge-warning text-[10px] font-black">LaTeX</span>
                                @elseif($submission->manuscript_format === 'docx')
                                    <span class="badge badge-info text-[10px] font-black">DOCX</span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <a href="{{ route('submissions.show', $submission) }}" class="block font-bold text-navy hover:text-orange text-sm leading-snug">
                                {{ $submission->title }}
                            </a>
                            <p class="mt-1 text-xs text-muted truncate">{{ $submission->conference->name }}</p>
                            @if($submission->is_flagged_duplicate)
                                <span class="mt-1 inline-block rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">⚠️ Potential Duplicate</span>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <x-status-badge :status="$submission->status" />
                            <span class="text-xs text-muted font-medium">
                                Submitted: {{ $submission->submitted_at?->format('d M Y') ?? '-' }}
                            </span>
                        </div>

                        <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3 text-xs grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div class="min-w-0">
                                <span class="text-muted block text-[11px]">Editor PIC</span>
                                @if($submission->editor)
                                    <button type="button" @click="activePic = staffMap[{{ $submission->editor->id }}]" class="mt-0.5 truncate font-bold text-navy hover:text-orange text-left block w-full">
                                        👤 {{ $submission->editor->name }}
                                    </button>
                                @else
                                    <p class="mt-0.5 text-muted">No editor assigned</p>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <span class="text-muted block text-[11px]">Reviewer PIC</span>
                                @if($submission->reviewer)
                                    <button type="button" @click="activePic = staffMap[{{ $submission->reviewer->id }}]" class="mt-0.5 truncate font-bold text-navy hover:text-orange text-left block w-full">
                                        🔍 {{ $submission->reviewer->name }}
                                    </button>
                                @else
                                    <p class="mt-0.5 text-muted">Reviewer: Unassigned</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-1">
                            <button type="button" class="text-xs font-bold text-orange" x-on:click="open = !open" x-text="open ? 'Close summary −' : 'Summary info +'">
                                Summary info +
                            </button>
                            <a href="{{ route('submissions.show', $submission) }}" class="btn btn-secondary px-3 py-1.5 text-xs">
                                Details ↗
                            </a>
                        </div>

                        <div x-cloak x-show="open" x-collapse class="rounded-xl bg-warm p-4 text-xs space-y-2.5">
                            <div><span class="text-muted font-bold block">Primary Author</span><p class="font-semibold text-navy">{{ $submission->corresponding_author_name }} ({{ $submission->corresponding_author_email }})</p></div>
                            <div><span class="text-muted font-bold block">Manuscript Format</span><p class="font-semibold text-navy">{{ $submission->manuscript_format === 'latex' ? 'LaTeX (ZIP)' : ($submission->manuscript_format === 'docx' ? 'Microsoft Word (DOCX)' : 'Not confirmed') }}</p></div>
                            @if($submission->deadline_at)
                                <div><span class="text-muted font-bold block">Deadline</span><p class="font-semibold {{ $submission->isOverdue() ? 'text-danger font-bold' : 'text-navy' }}">{{ $submission->deadline_at->format('d M Y') }}</p></div>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="p-8 text-center text-sm text-muted">No papers match the selected filters.</p>
                @endforelse
            </div>
        </div>
        <div class="mt-6">{{ $submissions->links() }}</div>

        <!-- PIC Details Modal -->
        <div x-show="activePic" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 overflow-y-auto">
            <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-5 sm:p-6 shadow-2xl my-auto" @click.away="activePic = null">
                <div class="flex items-start justify-between border-b border-navy/10 pb-4">
                    <div class="min-w-0 pr-2">
                        <p class="eyebrow">Staff Info &amp; Workload</p>
                        <h3 class="text-lg sm:text-xl font-black text-navy truncate" x-text="activePic?.name"></h3>
                        <p class="mt-0.5 text-xs text-muted break-words" x-text="activePic?.job_title + ' · ' + activePic?.affiliation"></p>
                    </div>
                    <button type="button" @click="activePic = null" class="text-muted hover:text-navy text-xl font-bold p-1">✕</button>
                </div>
                <div class="mt-4 space-y-4 text-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl bg-warm p-4">
                        <div class="min-w-0">
                            <span class="text-xs font-bold text-muted block">Email</span>
                            <p class="font-semibold text-navy truncate text-xs sm:text-sm" x-text="activePic?.email"></p>
                        </div>
                        <div class="min-w-0">
                            <span class="text-xs font-bold text-muted block">WhatsApp / Phone</span>
                            <template x-if="activePic?.whatsapp">
                                <a :href="'https://wa.me/' + activePic.whatsapp" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-bold text-emerald-600 hover:underline text-xs sm:text-sm">
                                    📱 <span x-text="activePic.whatsapp_raw || activePic.whatsapp"></span> ↗
                                </a>
                            </template>
                            <template x-if="!activePic?.whatsapp">
                                <p class="text-muted text-xs">Not configured</p>
                            </template>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-bold text-navy text-xs uppercase tracking-wider mb-2">Conference Memberships &amp; Assignments</h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            <template x-for="mem in activePic?.memberships" :key="mem.conference_slug">
                                <div class="flex items-center justify-between rounded-xl border border-navy/10 p-3 text-xs">
                                    <div class="min-w-0 pr-2">
                                        <p class="font-bold text-navy truncate" x-text="mem.conference_name"></p>
                                        <span class="badge badge-primary text-[10px] mt-1" x-text="mem.role_label"></span>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="font-black text-navy text-sm" x-text="mem.total_papers_count + ' Papers'"></p>
                                        <p class="text-[10px] text-muted" x-text="'Ed: ' + mem.editor_papers_count + ' | Rev: ' + mem.reviewer_papers_count"></p>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!activePic?.memberships?.length">
                                <p class="text-xs text-muted">Not registered in active conferences.</p>
                            </template>
                        </div>
                    </div>

                    <div class="rounded-xl border border-navy/10 p-4 bg-slate-50 flex items-center justify-between">
                        <span class="font-bold text-navy text-xs uppercase">Total Papers Assigned (Overall)</span>
                        <span class="badge badge-primary text-sm font-black px-3 py-1" x-text="(activePic?.total_assigned_papers || 0) + ' Papers'"></span>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" @click="activePic = null" class="btn btn-secondary text-xs px-5 py-2">Close</button>
                </div>
            </div>
        </div>

        <!-- Bulk Assign Modal -->
        <div x-data="{ show: false }" @open-bulk-assign.window="show = true" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 overflow-y-auto">
            <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-5 sm:p-6 shadow-2xl my-auto" @click.away="show = false">
                <h3 class="text-lg font-black text-slate-900">Bulk Assign PIC &amp; Deadline</h3>
                <p class="mt-1 text-xs text-slate-500">Apply assignments for <span x-text="selected.length"></span> selected papers.</p>
                <form method="POST" action="{{ route('submissions.bulk-assign') }}" class="mt-4 space-y-4">
                    @csrf
                    <template x-for="id in selected">
                        <input type="hidden" name="submission_ids[]" :value="id">
                    </template>
                    <div>
                        <label class="form-label">Assign Editor PIC</label>
                        <select class="form-input" name="editor_id">
                            <option value="">-- Keep Unchanged --</option>
                            @foreach($staff as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Assign Reviewer PIC</label>
                        <select class="form-input" name="reviewer_id">
                            <option value="">-- Keep Unchanged --</option>
                            @foreach($staff as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Manuscript Format</label>
                        <select class="form-input" name="manuscript_format">
                            <option value="">-- Keep Unchanged --</option>
                            <option value="docx">Microsoft Word (DOCX)</option>
                            <option value="latex">LaTeX (ZIP)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Deadline</label>
                        <input type="date" class="form-input" name="deadline_at">
                    </div>
                    <div>
                        <label class="form-label">Reassignment Reason (if replacing PIC)</label>
                        <input type="text" class="form-input" name="reassignment_reason" placeholder="e.g. Workload rebalancing">
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="show = false" class="btn btn-ghost">Cancel</button>
                        <button class="btn btn-primary">Apply Bulk Assignment</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Status Update Modal -->
        <div x-data="{ show: false }" @open-bulk-status.window="show = true" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 overflow-y-auto">
            <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-5 sm:p-6 shadow-2xl my-auto" @click.away="show = false">
                <h3 class="text-lg font-black text-slate-900">Bulk Update Status</h3>
                <p class="mt-1 text-xs text-slate-500">Update status for <span x-text="selected.length"></span> selected papers.</p>
                <form method="POST" action="{{ route('submissions.bulk-status') }}" class="mt-4 space-y-4">
                    @csrf
                    <template x-for="id in selected">
                        <input type="hidden" name="submission_ids[]" :value="id">
                    </template>
                    <div>
                        <label class="form-label">Bulk Action</label>
                        <select class="form-input" name="action" required>
                            <option value="accept">Validate &amp; Ready for Assignment</option>
                            <option value="reject">Reject Paper</option>
                            <option value="withdraw">Withdraw Paper</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Action Notes</label>
                        <textarea class="form-input min-h-20" name="note" placeholder="Optional notes..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="show = false" class="btn btn-ghost">Cancel</button>
                        <button class="btn btn-primary">Execute Bulk Status Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
