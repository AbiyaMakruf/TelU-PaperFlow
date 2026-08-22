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

    <!-- Quick Presets Bar -->
    <div class="mt-6 flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
        @php
            $activePreset = request('preset', '');
            $presetUrl = fn (?string $p) => route('submissions.index', array_merge(
                request()->except(['preset', 'page', 'status']),
                $p ? ['preset' => $p] : []
            ));
        @endphp

        <a href="{{ $presetUrl(null) }}" class="px-3.5 py-2 text-xs rounded-xl font-black transition shrink-0 flex items-center gap-1.5 shadow-2xs {{ $activePreset === '' && !request('status') ? 'bg-navy text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
            <span>All Papers</span>
            <span class="px-1.5 py-0.5 rounded-md text-[10px] {{ $activePreset === '' && !request('status') ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">{{ number_format($totalAllCount) }}</span>
        </a>

        <a href="{{ $presetUrl('my_tasks') }}" class="px-3.5 py-2 text-xs rounded-xl font-black transition shrink-0 flex items-center gap-1.5 shadow-2xs {{ $activePreset === 'my_tasks' ? 'bg-orange text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
            <span>My Assigned Tasks</span>
            <span class="px-1.5 py-0.5 rounded-md text-[10px] {{ $activePreset === 'my_tasks' ? 'bg-white/20 text-white' : 'bg-orange/10 text-orange-dark font-extrabold' }}">{{ number_format($myTasksCount) }}</span>
        </a>

        <a href="{{ $presetUrl('revision') }}" class="px-3.5 py-2 text-xs rounded-xl font-black transition shrink-0 flex items-center gap-1.5 shadow-2xs {{ $activePreset === 'revision' ? 'bg-rose-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
            <span>Waiting Author Revision</span>
            <span class="px-1.5 py-0.5 rounded-md text-[10px] {{ $activePreset === 'revision' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-700' }}">{{ number_format($waitingRevisionCount) }}</span>
        </a>

        <a href="{{ $presetUrl('edas') }}" class="px-3.5 py-2 text-xs rounded-xl font-black transition shrink-0 flex items-center gap-1.5 shadow-2xs {{ $activePreset === 'edas' ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
            <span>Ready for EDAS</span>
            <span class="px-1.5 py-0.5 rounded-md text-[10px] {{ $activePreset === 'edas' ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-700' }}">{{ number_format($readyEdasCount) }}</span>
        </a>
    </div>

    <!-- Advanced Filter Card -->
    <div x-data="{ mobileFilterOpen: {{ request()->except(['preset', 'search', 'page']) ? 'true' : 'false' }}, showDateFilter: {{ request('date_from') || request('date_to') ? 'true' : 'false' }} }" class="card mt-4 p-4 sm:p-5">
        <button type="button" @click="mobileFilterOpen = !mobileFilterOpen" class="flex w-full items-center justify-between font-bold text-navy md:hidden">
            <span class="flex items-center gap-2 text-xs sm:text-sm">
                <span>⚙️ Advanced Filters</span>
                @if(request()->except(['preset', 'search', 'page']))
                    <span class="badge badge-primary text-[10px]">Active</span>
                @endif
            </span>
            <span class="text-xs text-orange font-bold" x-text="mobileFilterOpen ? 'Close −' : 'Open Filter +'"></span>
        </button>

        <form x-show="mobileFilterOpen || window.innerWidth >= 768" x-collapse class="mt-4 md:mt-0 space-y-4">
            @if(request('preset'))
                <input type="hidden" name="preset" value="{{ request('preset') }}">
            @endif
            @if(request('search'))
                <input type="hidden" name="search" value="{{ request('search') }}">
            @endif

            <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
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
                    <label class="form-label">Conference</label>
                    <select class="form-input" name="conference">
                        <option value="">All conferences</option>
                        @foreach($conferences as $conference)
                            <option value="{{ $conference->id }}" @selected(request('conference') === $conference->id)>{{ $conference->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Collapsible Date Range -->
            <div class="pt-1">
                <button type="button" @click="showDateFilter = !showDateFilter" class="text-xs font-bold text-slate-600 hover:text-navy inline-flex items-center gap-1.5 transition">
                    <span>📅 Filter by Submission Date</span>
                    <span class="text-[10px] text-orange" x-text="showDateFilter ? '▲ Hide' : '▼ Show'"></span>
                </button>

                <div x-show="showDateFilter" x-collapse class="mt-3 grid gap-4 grid-cols-1 sm:grid-cols-2 max-w-xl">
                    <div>
                        <label class="form-label">Submitted From</label>
                        <input class="form-input" type="date" name="date_from" value="{{ request('date_from') }}">
                    </div>
                    <div>
                        <label class="form-label">Submitted To</label>
                        <input class="form-input" type="date" name="date_to" value="{{ request('date_to') }}">
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-navy/8">
                <button type="submit" class="btn btn-primary px-6 w-full sm:w-auto">Apply Filter</button>
                @if(request()->query())
                    <a class="btn btn-ghost w-full sm:w-auto text-center" href="{{ route('submissions.index') }}">Reset All Filters</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Main Table Container with Instant Live Search -->
    <div x-data="papersManager({{ json_encode($staffData) }}, '{{ request('search', '') }}')">
        <!-- Instant Live Search Bar & Show Per Page Toolbar -->
        <div class="mt-5 card p-3 sm:p-4 bg-white border border-slate-200 shadow-2xs rounded-2xl flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <!-- Search Box -->
            <div class="relative flex-1 min-w-0">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input 
                    type="text" 
                    x-model="searchQuery" 
                    @input="handleSearchInput()" 
                    placeholder="Live search by paper ID, title, author name, or email..." 
                    class="form-input !pl-12 sm:!pl-14 pr-10 py-2.5 text-xs sm:text-sm w-full rounded-xl border-slate-200 focus:border-navy"
                >
                <button 
                    type="button" 
                    x-show="searchQuery.length > 0" 
                    @click="clearSearch()" 
                    x-cloak 
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-navy text-base font-bold"
                    title="Clear search"
                >
                    &times;
                </button>
            </div>

            <!-- Controls on the right: Show Per Page + Total count + Loading -->
            <div class="flex items-center justify-between md:justify-end gap-3 text-xs text-slate-600 shrink-0">
                <!-- Show Per Page Selector -->
                <form method="GET" action="{{ route('submissions.index') }}" class="flex items-center gap-1.5 font-bold">
                    @foreach(request()->except(['per_page', 'page']) as $key => $val)
                        @if(is_array($val))
                            @foreach($val as $v)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                        @endif
                    @endforeach
                    <label for="per_page_select_top" class="whitespace-nowrap text-[11px] sm:text-xs text-slate-500 font-bold">Per page:</label>
                    <select id="per_page_select_top" name="per_page" onchange="this.form.submit()" class="form-input text-xs py-2 px-2.5 rounded-xl w-auto font-bold bg-slate-50 border-slate-200 hover:bg-slate-100 transition shadow-2xs">
                        <option value="10" @selected(request('per_page') === '10')>10</option>
                        <option value="20" @selected(request('per_page') === '20' || !request('per_page'))>20</option>
                        <option value="30" @selected(request('per_page') === '30')>30</option>
                        <option value="40" @selected(request('per_page') === '40')>40</option>
                        <option value="50" @selected(request('per_page') === '50')>50</option>
                        <option value="all" @selected(request('per_page') === 'all')>All</option>
                    </select>
                </form>

                <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

                <!-- Status & Count -->
                <div class="flex items-center gap-2">
                    <div x-show="isLoading" x-cloak class="flex items-center gap-1.5 text-orange font-bold text-xs animate-pulse">
                        <svg class="animate-spin size-3.5" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span class="hidden sm:inline">Searching...</span>
                    </div>
                    <div id="submissions-total-count" class="font-bold text-navy text-[11px] sm:text-xs whitespace-nowrap">
                        {{ $submissions->firstItem() ?? 0 }} - {{ $submissions->lastItem() ?? 0 }} of {{ $submissions->total() }} papers
                    </div>
                </div>
            </div>
        </div>

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
                @if(Route::has('submissions.bulk-send-portal-link'))
                <form method="POST" action="{{ route('submissions.bulk-send-portal-link') }}" class="inline-block w-full sm:w-auto">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="submission_ids[]" :value="id">
                    </template>
                    <button type="submit" class="btn text-xs py-1.5 px-3 w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold border-0 shadow-sm transition">
                        ✉️ Bulk Send Portal Links
                    </button>
                </form>
                @endif
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

        <div id="submissions-table-container" class="card mt-6 overflow-hidden">
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
                            <th class="text-center"><a href="{{ $sortUrl('status') }}">Status ↕</a></th>
                            <th class="text-center">Portal Link</th>
                            <th><a href="{{ $sortUrl('submitted_at') }}">Submitted ↕</a></th>
                        </tr>
                    </thead>
                    @forelse ($submissions as $submission)
                        <tbody x-data="{ open: false }" class="border-b border-navy/8">
                        <tr @click="open = !open" class="cursor-pointer hover:bg-slate-50/80 transition" title="Click row to view quick summary">
                            <td @click.stop>
                                <input type="checkbox" value="{{ $submission->id }}" x-model="selected">
                            </td>
                            <td>
                                <a href="{{ route('submissions.show', $submission) }}" @click.stop class="font-black text-navy hover:text-orange hover:underline block">
                                    {{ $submission->paper_id ?: $submission->paper_code }}
                                </a>
                                <p class="mt-1 text-xs text-muted">{{ $submission->conference?->name ?? 'Unknown Conference' }}</p>
                                @if($submission->is_flagged_duplicate)
                                    <span class="mt-1 inline-block rounded bg-rose-100 px-2 py-0.5 text-[10px] font-extrabold text-rose-700" title="{{ $submission->duplicate_notes }}">⚠️ Potential Duplicate</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('submissions.show', $submission) }}" @click.stop class="block max-w-lg font-bold text-navy hover:text-orange hover:underline" title="{{ $submission->title }}">
                                    {{ $submission->title }}
                                </a>
                            </td>
                            <td>
                                @if($submission->manuscript_format === 'latex')
                                    <span class="badge badge-warning text-[11px] font-black">LaTeX</span>
                                @elseif($submission->manuscript_format === 'docx')
                                    <span class="badge badge-info text-[11px] font-black">DOCX</span>
                                @else
                                    <span class="badge badge-neutral text-[11px] text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($submission->editor)
                                    <button type="button" @click.stop="activePic = staffMap[{{ $submission->editor->id }}]" class="font-bold text-navy hover:text-orange hover:underline text-left block">
                                        👤 {{ $submission->editor->name }}
                                    </button>
                                @else
                                    <p class="text-muted text-xs">No editor assigned</p>
                                @endif
                                @if($submission->reviewer)
                                    <button type="button" @click.stop="activePic = staffMap[{{ $submission->reviewer->id }}]" class="mt-1 text-xs text-muted hover:text-orange hover:underline text-left block">
                                        🔍 Reviewer: {{ $submission->reviewer->name }}
                                    </button>
                                @else
                                    <p class="mt-1 text-xs text-muted">No reviewer assigned</p>
                                @endif
                            </td>
                            <td class="text-center"><x-status-badge :submission="$submission" /></td>
                            <td @click.stop class="text-center">
                                <div class="inline-flex items-center gap-2">
                                    @if($submission->portalLinkSent())
                                        <span class="size-2.5 rounded-full bg-emerald-500 shrink-0 shadow-2xs" title="Sent at {{ $submission->portalLinkSentAt()?->format('d M Y, H:i') }}" aria-hidden="true"></span>
                                        <button type="button" onclick="openPortalLinkModal('{{ route('submissions.send-portal-link', $submission) }}', '{{ e($submission->paper_id ?: $submission->paper_code) }}', '{{ addslashes($submission->title) }}', '{{ e($submission->corresponding_author_name) }}', '{{ e($submission->corresponding_author_email) }}', true, '{{ $submission->portalLinkSentAt()?->format('d M Y, H:i') }}')" class="btn btn-secondary text-xs py-1 px-2.5 font-bold text-slate-700 hover:text-orange hover:border-orange shadow-2xs" title="Sent at {{ $submission->portalLinkSentAt()?->format('d M Y, H:i') }} &bull; Click to confirm resend link">
                                            ✉️ Resend Link
                                        </button>
                                    @else
                                        <span class="size-2.5 rounded-full bg-amber-500 animate-pulse shrink-0 shadow-2xs" title="Portal link email has not been sent yet" aria-hidden="true"></span>
                                        <button type="button" onclick="openPortalLinkModal('{{ route('submissions.send-portal-link', $submission) }}', '{{ e($submission->paper_id ?: $submission->paper_code) }}', '{{ addslashes($submission->title) }}', '{{ e($submission->corresponding_author_name) }}', '{{ e($submission->corresponding_author_email) }}', false, '')" class="btn text-xs py-1 px-2.5 font-extrabold bg-amber-50 text-amber-900 border border-amber-300 hover:bg-amber-100 hover:border-amber-400 shadow-2xs" title="Not sent yet &bull; Click to confirm send link">
                                            ✉️ Send Link
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $submission->submitted_at?->format('d M Y') ?? '-' }}@if($submission->deadline_at)<p class="mt-1 text-xs {{ $submission->isOverdue() ? 'text-danger font-bold':'text-muted' }}">Deadline {{ $submission->formattedDeadline('d M Y') }}</p>@endif</td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="8" class="bg-gradient-to-r from-slate-50 via-indigo-50/30 to-slate-50 px-6 py-4 border-y border-indigo-100/80 shadow-inner">
                                <div class="flex flex-wrap items-center justify-between gap-4 text-xs">
                                    <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                                        <div class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-1.5 border border-slate-200/80 shadow-2xs">
                                            <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Code</span>
                                            <span class="font-black text-navy">{{ $submission->paper_code }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-1.5 border border-slate-200/80 shadow-2xs">
                                            <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Author</span>
                                            <span class="font-black text-navy">{{ $submission->corresponding_author_name }}</span>
                                            <span class="text-slate-400 text-[11px]">({{ $submission->corresponding_author_email }})</span>
                                        </div>
                                        <div class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-1.5 border border-slate-200/80 shadow-2xs">
                                            <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Format</span>
                                            <span class="font-black text-navy">{{ $submission->manuscript_format === 'latex' ? 'LaTeX' : ($submission->manuscript_format === 'docx' ? 'DOCX' : 'Unconfirmed') }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-1.5 border border-slate-200/80 shadow-2xs">
                                            <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Pages</span>
                                            <span class="font-black text-navy">{{ $submission->initial_page_count ? $submission->initial_page_count.' pp' : '-' }} → {{ $submission->final_page_count ? $submission->final_page_count.' pp' : '-' }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-1.5 border border-slate-200/80 shadow-2xs">
                                            <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Co-Authors</span>
                                            <span class="font-black text-navy">{{ $submission->authors->count() }} author(s)</span>
                                        </div>
                                    </div>
                                    <div @click.stop>
                                        <a href="{{ route('submissions.show', $submission) }}" class="btn btn-primary px-3.5 py-1.5 text-xs font-black shrink-0 shadow-xs flex items-center gap-1.5">
                                            <span>📄 Open Full Workspace</span>
                                            <span class="text-[10px]">↗</span>
                                        </a>
                                    </div>
                                </div>
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
                    <article x-data="{ open: false }" @click="open = !open" class="p-4 space-y-3 cursor-pointer hover:bg-slate-50/70 transition">
                        <div class="flex items-start justify-between gap-2 min-w-0">
                            <div class="flex items-center gap-2 min-w-0" @click.stop>
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
                            <a href="{{ route('submissions.show', $submission) }}" @click.stop class="block font-bold text-navy hover:text-orange text-sm leading-snug">
                                {{ $submission->title }}
                            </a>
                            <p class="mt-1 text-xs text-muted truncate">{{ $submission->conference?->name ?? 'Unknown Conference' }}</p>
                            @if($submission->is_flagged_duplicate)
                                <span class="mt-1 inline-block rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">⚠️ Potential Duplicate</span>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <x-status-badge :submission="$submission" />
                            <span class="text-xs text-muted font-medium">
                                Submitted: {{ $submission->submitted_at?->format('d M Y') ?? '-' }}
                            </span>
                        </div>

                        <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3 text-xs space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div class="min-w-0">
                                    <span class="text-muted block text-[11px]">Editor PIC</span>
                                    @if($submission->editor)
                                        <button type="button" @click.stop="activePic = staffMap[{{ $submission->editor->id }}]" class="mt-0.5 truncate font-bold text-navy hover:text-orange text-left block w-full">
                                            👤 {{ $submission->editor->name }}
                                        </button>
                                    @else
                                        <p class="mt-0.5 text-muted">No editor assigned</p>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <span class="text-muted block text-[11px]">Reviewer PIC</span>
                                    @if($submission->reviewer)
                                        <button type="button" @click.stop="activePic = staffMap[{{ $submission->reviewer->id }}]" class="mt-0.5 truncate font-bold text-navy hover:text-orange text-left block w-full">
                                            🔍 {{ $submission->reviewer->name }}
                                        </button>
                                    @else
                                        <p class="mt-0.5 text-muted">No reviewer assigned</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-2 border-t border-slate-200/60 pt-2" @click.stop>
                                <div class="flex items-center gap-2 min-w-0">
                                    @if($submission->portalLinkSent())
                                        <span class="size-2.5 rounded-full bg-emerald-500 shrink-0 shadow-2xs" title="Sent at {{ $submission->portalLinkSentAt()?->format('d M Y, H:i') }}" aria-hidden="true"></span>
                                    @else
                                        <span class="size-2.5 rounded-full bg-amber-500 animate-pulse shrink-0 shadow-2xs" title="Portal link email has not been sent yet" aria-hidden="true"></span>
                                    @endif
                                    <span class="text-[11px] text-muted font-bold truncate">Portal Link Email</span>
                                </div>
                                @if($submission->portalLinkSent())
                                    <button type="button" onclick="openPortalLinkModal('{{ route('submissions.send-portal-link', $submission) }}', '{{ e($submission->paper_id ?: $submission->paper_code) }}', '{{ addslashes($submission->title) }}', '{{ e($submission->corresponding_author_name) }}', '{{ e($submission->corresponding_author_email) }}', true, '{{ $submission->portalLinkSentAt()?->format('d M Y, H:i') }}')" class="btn btn-secondary text-xs py-1 px-2.5 font-bold text-slate-700 hover:text-orange shadow-2xs" title="Sent at {{ $submission->portalLinkSentAt()?->format('d M Y, H:i') }} &bull; Click to confirm resend link">
                                        ✉️ Resend Link
                                    </button>
                                @else
                                    <button type="button" onclick="openPortalLinkModal('{{ route('submissions.send-portal-link', $submission) }}', '{{ e($submission->paper_id ?: $submission->paper_code) }}', '{{ addslashes($submission->title) }}', '{{ e($submission->corresponding_author_name) }}', '{{ e($submission->corresponding_author_email) }}', false, '')" class="btn text-xs py-1 px-2.5 font-extrabold bg-amber-50 text-amber-900 border border-amber-300 hover:bg-amber-100 hover:border-amber-400 shadow-2xs" title="Not sent yet &bull; Click to confirm send link">
                                        ✉️ Send Link
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-1">
                            <span class="text-xs font-bold text-orange select-none" x-text="open ? 'Close summary −' : 'Click card for summary +'">
                                Click card for summary +
                            </span>
                            <div class="flex items-center gap-2" @click.stop>
                                <a href="{{ route('submissions.show', $submission) }}" class="btn btn-secondary px-3 py-1.5 text-xs">
                                    Details ↗
                                </a>
                            </div>
                        </div>

                        <div x-cloak x-show="open" x-collapse class="rounded-xl bg-warm p-4 text-xs space-y-2.5">
                            <div><span class="text-muted font-bold block">Primary Author</span><p class="font-semibold text-navy">{{ $submission->corresponding_author_name }} ({{ $submission->corresponding_author_email }})</p></div>
                            <div><span class="text-muted font-bold block">Manuscript Format</span><p class="font-semibold text-navy">{{ $submission->manuscript_format === 'latex' ? 'LaTeX' : ($submission->manuscript_format === 'docx' ? 'DOCX' : 'Not confirmed') }}</p></div>
                            @if($submission->deadline_at)
                                <div><span class="text-muted font-bold block">Deadline</span><p class="font-semibold {{ $submission->isOverdue() ? 'text-danger font-bold' : 'text-navy' }}">{{ $submission->formattedDeadline('d M Y') }}</p></div>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="p-8 text-center text-sm text-muted">No papers match the selected filters.</p>
                @endforelse
            </div>
        </div>
        <div id="submissions-pagination-container" class="mt-6 flex items-center justify-end">
            {{ $submissions->links() }}
        </div>

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

        <!-- Single Send / Resend Author Portal Link Confirmation Modal -->
        <div id="portal-link-confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-navy/60 backdrop-blur-xs">
            <div class="card w-full max-w-lg p-6 bg-white space-y-4 shadow-2xl rounded-2xl border border-slate-200" @click.away="closePortalLinkModal()">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="size-9 rounded-xl bg-orange/15 text-orange flex items-center justify-center text-lg font-bold">✉️</span>
                        <div>
                            <h3 id="portal-modal-title" class="text-base font-black text-navy">Confirm Send Author Portal Link</h3>
                            <p class="text-[11px] text-muted">Send private author portal access token link via email</p>
                        </div>
                    </div>
                    <button type="button" onclick="closePortalLinkModal()" class="text-slate-400 hover:text-navy font-bold text-lg">&times;</button>
                </div>

                <form id="portal-link-confirm-form" method="POST" action="" class="space-y-4">
                    @csrf
                    <div class="space-y-1.5 bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-muted font-extrabold uppercase text-[10px]">Paper ID / Code:</span>
                            <span id="portal-modal-paper-id" class="font-mono font-bold text-navy text-[11px]"></span>
                        </div>
                        <span class="text-muted font-extrabold uppercase text-[10px] block pt-1">Paper Title:</span>
                        <p id="portal-modal-paper-title" class="font-bold text-navy text-xs leading-snug break-words"></p>
                    </div>

                    <div class="space-y-1 bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-xs">
                        <span class="text-muted font-extrabold uppercase text-[10px]">Corresponding Author Recipient:</span>
                        <p id="portal-modal-author" class="font-bold text-navy text-xs break-words"></p>
                    </div>

                    <div id="portal-modal-status-box" class="p-3.5 rounded-xl border text-xs">
                        <p id="portal-modal-status-text" class="font-medium text-xs leading-snug"></p>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" onclick="closePortalLinkModal()" class="btn border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs py-2.5 px-4 font-bold rounded-xl">
                            Cancel
                        </button>
                        <button type="submit" id="portal-modal-submit-btn" class="btn bg-orange hover:bg-orange-dark text-white text-xs font-black py-2.5 px-5 shadow-sm rounded-xl flex items-center gap-2">
                            <span>🚀 Confirm &amp; Send Portal Link</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    window.papersManager = function(staffData, initialSearch) {
        return {
            selected: [],
            bulkAction: '',
            activePic: null,
            staffMap: staffData,
            searchQuery: initialSearch || '',
            isLoading: false,
            searchTimeout: null,

            handleSearchInput() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.performLiveSearch();
                }, 300);
            },

            clearSearch() {
                this.searchQuery = '';
                this.performLiveSearch();
            },

            performLiveSearch() {
                this.isLoading = true;
                const url = new URL(window.location.href);
                if (this.searchQuery.trim() !== '') {
                    url.searchParams.set('search', this.searchQuery.trim());
                } else {
                    url.searchParams.delete('search');
                }
                url.searchParams.delete('page');

                window.history.replaceState({}, '', url.toString());

                fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newTable = doc.getElementById('submissions-table-container');
                    const currentTable = document.getElementById('submissions-table-container');
                    if (newTable && currentTable) {
                        currentTable.innerHTML = newTable.innerHTML;
                    }
                    const newPagination = doc.getElementById('submissions-pagination-container');
                    const currentPagination = document.getElementById('submissions-pagination-container');
                    if (newPagination && currentPagination) {
                        currentPagination.innerHTML = newPagination.innerHTML;
                    }
                    const newCount = doc.getElementById('submissions-total-count');
                    const currentCount = document.getElementById('submissions-total-count');
                    if (newCount && currentCount) {
                        currentCount.innerHTML = newCount.innerHTML;
                    }
                })
                .catch(err => console.error('Live search error:', err))
                .finally(() => {
                    this.isLoading = false;
                });
            }
        };
    };

    window.openPortalLinkModal = function(url, paperId, paperTitle, authorName, authorEmail, isSent, sentAt) {
        const modal = document.getElementById('portal-link-confirm-modal');
        const form = document.getElementById('portal-link-confirm-form');
        const titleEl = document.getElementById('portal-modal-title');
        const paperIdEl = document.getElementById('portal-modal-paper-id');
        const paperTitleEl = document.getElementById('portal-modal-paper-title');
        const authorEl = document.getElementById('portal-modal-author');
        const statusBox = document.getElementById('portal-modal-status-box');
        const statusText = document.getElementById('portal-modal-status-text');
        const submitBtn = document.getElementById('portal-modal-submit-btn');

        if (!modal || !form) return;

        form.action = url;
        paperIdEl.textContent = paperId;
        paperTitleEl.textContent = paperTitle;
        authorEl.textContent = authorName + ' (' + authorEmail + ')';

        if (isSent) {
            titleEl.textContent = 'Confirm Resend Author Portal Link';
            statusBox.className = 'p-3.5 rounded-xl border text-xs bg-emerald-50/90 border-emerald-200 text-emerald-900';
            statusText.innerHTML = '<strong>✓ Sent Previously:</strong> Last sent on <strong>' + sentAt + '</strong>. Re-sending will queue a new email notification containing the author portal link.';
            submitBtn.querySelector('span').textContent = '🔄 Confirm & Resend Portal Link';
        } else {
            titleEl.textContent = 'Confirm Send Author Portal Link';
            statusBox.className = 'p-3.5 rounded-xl border text-xs bg-amber-50/90 border-amber-200 text-amber-900';
            statusText.innerHTML = '<strong>⏳ First-Time Send:</strong> This paper has not received an author portal access link email yet.';
            submitBtn.querySelector('span').textContent = '🚀 Confirm & Send Portal Link';
        }

        modal.classList.remove('hidden');
    };

    window.closePortalLinkModal = function() {
        const modal = document.getElementById('portal-link-confirm-modal');
        if (modal) modal.classList.add('hidden');
    };
    </script>
</x-layouts.app>
