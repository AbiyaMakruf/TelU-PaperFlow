<x-layouts.public :title="$submission->paper_code">
    <div class="mx-auto max-w-5xl">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div class="min-w-0">
                <p class="eyebrow truncate">Author Portal &middot; {{ $submission->conference->name }}</p>
                <h1 class="page-title break-words">{{ $submission->paper_code }}</h1>
                <p class="page-subtitle break-words">{{ $submission->title }}</p>
            </div>
            <span class="badge badge-{{ $submission->status->color() }} self-start shrink-0 sm:self-auto">{{ $submission->status->label() }}</span>
        </div>

        <div class="mt-6 grid gap-6 sm:mt-8 lg:grid-cols-[1.4fr_.6fr]">
            <section class="min-w-0 space-y-6">
                <!-- Edit Detail Submission Card -->
                <div class="card p-4 sm:p-6" x-data="{ openEdit: false }">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="text-lg font-black text-navy">Submission Details</h2>
                            <p class="text-xs text-muted">Paper ID, paper title, author contact information, and co-authors list.</p>
                        </div>
                        @if(in_array($submission->status, [\App\Enums\SubmissionStatus::Done, \App\Enums\SubmissionStatus::Withdrawn, \App\Enums\SubmissionStatus::Rejected], true))
                            <button type="button" disabled class="btn text-xs bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed shrink-0 self-start sm:self-auto" title="Submission details cannot be edited after completion">
                                Edit Submission Details
                            </button>
                        @else
                            <button type="button" @click="openEdit = !openEdit" class="btn btn-secondary text-xs shrink-0 self-start sm:self-auto">
                                <span x-text="openEdit ? 'Cancel Edit' : 'Edit Submission Details'"></span>
                            </button>
                        @endif
                    </div>

                    <div x-show="!openEdit" class="mt-5 space-y-3 text-sm">
                        <div class="min-w-0">
                            <span class="text-xs font-bold text-muted">Paper ID:</span>
                            <p class="mt-0.5 font-semibold text-navy break-words">{{ $submission->paper_id }}</p>
                        </div>
                        <div class="min-w-0 pt-1">
                            <span class="text-xs font-bold text-muted">Paper Title:</span>
                            <p class="mt-0.5 font-bold text-navy break-words">{{ $submission->title }}</p>
                        </div>
                        <div class="grid gap-3 pt-2 sm:grid-cols-2">
                            <div class="min-w-0">
                                <span class="text-xs font-bold text-muted">Author:</span>
                                <p class="mt-0.5 font-semibold text-navy break-words">{{ $submission->corresponding_author_name }}</p>
                                <p class="text-xs text-muted break-all">{{ $submission->corresponding_author_email }}</p>
                            </div>
                            <div class="min-w-0">
                                <span class="text-xs font-bold text-muted">Mobile / WhatsApp Number:</span>
                                <p class="mt-0.5 font-semibold text-navy break-all">{{ $submission->corresponding_author_phone ?: '-' }}</p>
                            </div>
                        </div>
                        @if($submission->authors->where('is_corresponding', false)->isNotEmpty())
                            <div class="min-w-0 pt-2">
                                <span class="text-xs font-bold text-muted">Co-Authors:</span>
                                <ul class="mt-1 space-y-1.5 list-disc list-inside text-xs text-slate-700">
                                    @foreach($submission->authors->where('is_corresponding', false) as $co)
                                        <li class="break-words">{{ $co->name }} ({{ $co->email ?: 'No email' }})</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @php
                            $finalFile = $submission->files->firstWhere('is_final', true);
                            $latestManuscript = $finalFile ?? $submission->files->where('file_category', 'editable_manuscript')->sortByDesc('version_number')->first() ?? $submission->files->sortByDesc('version_number')->first();
                            $latestGuidancePdf = $submission->files->where('version_number', $latestManuscript?->version_number)->firstWhere('file_category', 'revision_guidance_pdf')
                                ?? $submission->files->firstWhere('file_category', 'revision_guidance_pdf');
                        @endphp
                        @if($latestManuscript)
                            <div class="mt-6 pt-6 border-t border-navy/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5" style="padding-top: 1.25rem; margin-top: 1.25rem;">
                                <div class="min-w-0">
                                    <span class="text-xs font-extrabold text-navy">{{ $finalFile ? 'Final Approved Manuscript File:' : 'Latest Manuscript File:' }}</span>
                                    <p class="text-xs text-muted truncate mt-0.5">
                                        v{{ $latestManuscript->version_number }} &middot; {{ $latestManuscript->original_name }}
                                        @if($latestManuscript->is_final)
                                            <span class="badge badge-success text-[10px] ml-1.5 font-bold">Final Version</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('author.files.download', [$token, $latestManuscript]) }}" class="btn text-xs py-2.5 px-4 bg-orange hover:bg-orange-dark text-white font-extrabold shadow-2xs rounded-xl flex items-center gap-1.5 shrink-0 transition" title="Download manuscript version (v{{ $latestManuscript->version_number }})">
                                        <svg class="size-4 shrink-0 fill-current" viewBox="0 0 24 24">
                                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                                        </svg>
                                        <span>Download {{ $latestManuscript->is_final ? 'Final File' : 'Latest Manuscript' }} (v{{ $latestManuscript->version_number }})</span>
                                    </a>
                                    @if($latestGuidancePdf)
                                        <a href="{{ route('author.files.download', [$token, $latestGuidancePdf]) }}" class="btn text-xs py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold shadow-2xs rounded-xl flex items-center gap-1.5 shrink-0 transition" title="Download PDF Visual Guidance for revision">
                                            <svg class="size-4 shrink-0 fill-current" viewBox="0 0 24 24">
                                                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                                            </svg>
                                            <span>Download Guidance PDF (v{{ $latestGuidancePdf->version_number }})</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($submission->deadline_at && in_array($submission->status, [\App\Enums\SubmissionStatus::NeedsAuthorCorrection, \App\Enums\SubmissionStatus::WaitingAuthorRevision], true))
                            <div class="mt-4 pt-4 border-t border-navy/10 flex flex-wrap items-center justify-between gap-3" style="padding-top: 1rem; margin-top: 1rem;">
                                <div class="min-w-0">
                                    <span class="text-xs font-bold text-muted">Revision Deadline:</span>
                                    <p class="mt-0.5 text-xs font-extrabold {{ $submission->isOverdue() ? 'text-rose-700' : 'text-navy' }}">
                                        {{ $submission->deadline_at->timezone('Asia/Jakarta')->format('d F Y, 23:59 \G\M\T+7') }}
                                    </p>
                                </div>
                                @if($submission->isOverdue())
                                    <span class="badge badge-danger text-[11px] shrink-0">Overdue</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <form x-show="openEdit" x-cloak method="POST" action="{{ route('author.details.update', $token) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="min-w-0">
                            <label class="form-label">Paper Title *</label>
                            <input class="form-input min-w-0" name="title" value="{{ old('title', $submission->title) }}" required>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label class="form-label">Author Name *</label>
                                <input class="form-input min-w-0" name="author_name" value="{{ old('author_name', $submission->corresponding_author_name) }}" required>
                            </div>
                            <div class="min-w-0">
                                <label class="form-label">Author Email *</label>
                                <input class="form-input min-w-0" type="email" name="author_email" value="{{ old('author_email', $submission->corresponding_author_email) }}" required>
                            </div>
                        </div>
                        @php
                            $selectedPhoneCode = collect(array_keys($countryCodes))->sortByDesc(fn($code)=>strlen($code))->first(fn($code)=>str_starts_with($submission->corresponding_author_phone ?? '',$code)) ?? '+62';
                            $nationalPhone = ltrim(substr($submission->corresponding_author_phone ?? '',strlen($selectedPhoneCode)),'0');
                        @endphp
                        <div class="min-w-0">
                            <label class="form-label">Mobile / WhatsApp Number *</label>
                            <div class="grid gap-2 grid-cols-1 sm:grid-cols-[minmax(0,1.25fr)_minmax(0,1.75fr)]">
                                <select class="form-input px-2 min-w-0" name="author_phone_country_code" required>
                                    @foreach($countryCodes as $code=>$label)
                                        <option value="{{ $code }}" @selected(old('author_phone_country_code',$selectedPhoneCode)===$code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input class="form-input min-w-0" name="author_phone" value="{{ old('author_phone',$nationalPhone) }}" required>
                            </div>
                        </div>

                        <!-- Co-authors list editor -->
                        <div class="min-w-0 pt-2" x-data="{
                            coAuthors: {{ Js::from($submission->authors->where('is_corresponding', false)->values()->map(fn($a) => ['name' => $a->name, 'email' => $a->email])) }}
                        }">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="form-label mb-0">Co-Authors List</span>
                                <button type="button" @click="coAuthors.push({name: '', email: ''})" class="text-xs font-bold text-orange hover:underline shrink-0">+ Add Co-Author</button>
                            </div>
                            <template x-for="(co, index) in coAuthors" :key="index">
                                <div class="space-y-2 mb-3 p-3 bg-warm/50 rounded-xl sm:grid sm:grid-cols-2 sm:gap-2 sm:space-y-0 sm:items-center">
                                    <input class="form-input text-xs w-full min-w-0" :name="`co_authors[${index}][name]`" x-model="co.name" placeholder="Full Name *" required>
                                    <div class="flex items-center gap-2 w-full min-w-0">
                                        <input class="form-input text-xs flex-1 min-w-0" type="email" :name="`co_authors[${index}][email]`" x-model="co.email" placeholder="Email (Optional)">
                                        <button type="button" @click="coAuthors.splice(index, 1)" class="text-rose-600 font-bold px-2 py-1 hover:bg-rose-100 rounded-lg shrink-0" title="Remove">✕</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3 pt-3">
                            <button type="button" @click="openEdit = false" class="btn btn-ghost w-full sm:w-auto">Cancel</button>
                            <button class="btn btn-primary w-full sm:w-auto">Save Changes</button>
                        </div>
                    </form>
                </div>

                @if ($submission->status === \App\Enums\SubmissionStatus::WaitingAuthorRevision)
                    <div class="card p-4 sm:p-5 border-amber-300 bg-amber-50/80 text-xs text-amber-950 space-y-2.5 shadow-2xs">
                        <h3 class="font-extrabold text-amber-950 text-sm">Important Revision Instructions</h3>
                        <ul class="list-disc list-inside space-y-1.5 leading-relaxed text-amber-900 font-medium">
                            <li>Please inspect the <strong>Editorial Compliance Checklist Monitoring (Live)</strong> card below to see specific items requiring correction (marked with <strong class="text-rose-700 font-extrabold">✕ Revision Needed</strong>).</li>
                            <li><strong>Always use the Latest Manuscript File (v{{ $latestManuscript?->version_number ?? '1' }})</strong> as the base for your revisions, because the editorial team may have already performed initial formatting corrections on it.</li>
                            <li><strong>Only modify the specific items requested for correction</strong>. Please leave all other already compliant sections untouched.</li>
                        </ul>
                    </div>
                @endif

                {{-- Live Editorial Compliance Checklist Monitoring (Accordion) --}}
                @php
                    $editorialTemplates = $submission->conference->checklistTemplates->filter(fn ($t) => strtolower($t->stage->value ?? $t->stage) === 'editorial');
                @endphp
                @if($submission->editor_id !== null && $editorialTemplates->isNotEmpty())
                    <details class="card group p-4 sm:p-6 transition">
                        <summary class="flex cursor-pointer items-center justify-between gap-3 list-none font-black text-navy focus:outline-none select-none">
                            <div class="min-w-0">
                                <h2 class="text-base sm:text-lg font-black text-navy inline-flex items-center gap-2">
                                    <span>Editorial Compliance Checklist Monitoring (Live)</span>
                                </h2>
                                <p class="mt-0.5 text-xs text-muted font-normal">Paper format compliance status based on editorial team checks. Click to expand/collapse.</p>
                            </div>
                            <span class="rounded-full bg-slate-100 p-2 text-slate-500 group-open:rotate-180 transition-transform shrink-0">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </summary>

                        @php
                            $isRevisionStage = $submission->status === \App\Enums\SubmissionStatus::WaitingAuthorRevision;
                        @endphp
                        <div class="mt-4 pt-4 border-t border-slate-100 space-y-2">
                            @foreach($editorialTemplates as $tmpl)
                                @foreach($tmpl->items as $item)
                                    @php $res = isset($checklistResults) ? $checklistResults->get($item->id) : null; @endphp
                                    @php
                                        if ($res?->is_checked) {
                                            $cardBg = 'bg-emerald-50/50 border-emerald-200';
                                            $titleColor = 'text-emerald-900';
                                            $badgeClass = 'badge-success';
                                            $badgeText = '✓ Passed';
                                        } elseif ($isRevisionStage) {
                                            $cardBg = 'bg-rose-50/50 border-rose-200';
                                            $titleColor = 'text-rose-900';
                                            $badgeClass = 'badge-danger';
                                            $badgeText = '✕ Revision Needed';
                                        } else {
                                            $cardBg = 'bg-amber-50/50 border-amber-200';
                                            $titleColor = 'text-amber-900';
                                            $badgeClass = 'badge-warning';
                                            $badgeText = 'Under Review';
                                        }
                                    @endphp
                                    <div class="flex flex-col gap-2.5 p-3.5 rounded-xl border sm:flex-row sm:items-start sm:justify-between {{ $cardBg }}">
                                        <div class="min-w-0">
                                            <p class="text-xs font-extrabold break-words {{ $titleColor }}">{{ $item->title }}</p>
                                            @if($item->description)
                                                <p class="mt-0.5 text-[11px] text-slate-600 break-words">{{ $item->description }}</p>
                                            @endif
                                            @if(!$res?->is_checked && $res?->note)
                                                <p class="mt-1 text-[11px] font-semibold text-slate-800 break-words">Note: {{ $res->note }}</p>
                                            @endif
                                        </div>
                                        <span class="badge {{ $badgeClass }} shrink-0 self-start sm:self-auto">
                                            {{ $badgeText }}
                                        </span>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </details>
                @endif

                @if ($submission->feedback->isNotEmpty())
                    <details class="card group p-4 sm:p-6 transition">
                        <summary class="flex cursor-pointer items-center justify-between gap-3 list-none font-black text-navy focus:outline-none select-none">
                            <div class="min-w-0">
                                <h2 class="text-base sm:text-lg font-black text-navy inline-flex items-center gap-2">
                                    <span>Editorial Feedback &amp; Notes</span>
                                </h2>
                                <p class="mt-0.5 text-xs text-muted font-normal">Official evaluation feedback and revision requests sent by the editorial team. Click to expand/collapse.</p>
                            </div>
                            <span class="rounded-full bg-slate-100 p-2 text-slate-500 group-open:rotate-180 transition-transform shrink-0">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </summary>
                        <div class="mt-4 pt-4 border-t border-slate-100 space-y-3">
                            @foreach ($submission->feedback as $item)
                                <div class="rounded-xl bg-warm p-4 text-sm leading-6 break-words border border-navy/8">
                                    @if(str_contains($item->body, '<table'))
                                        <div>
                                            <p class="font-extrabold text-navy text-xs sm:text-sm">Itemized Editorial Compliance Checklist</p>
                                            <p class="mt-1 text-xs text-slate-700 leading-relaxed font-medium">
                                                The itemized checklist table sent for this revision request can be monitored in real-time under the 
                                                <strong>Editorial Compliance Checklist Monitoring (Live)</strong> card above. 
                                                Please make sure to download and use the <strong>Latest Manuscript File</strong> for your revision.
                                            </p>
                                        </div>
                                    @elseif(str_contains($item->body, '<div') || str_contains($item->body, '<p'))
                                        <div class="prose prose-sm max-w-none text-navy leading-relaxed">{!! $item->body !!}</div>
                                    @else
                                        <p class="whitespace-pre-line text-navy text-xs sm:text-sm leading-relaxed">{{ $item->body }}</p>
                                    @endif
                                    <p class="mt-3 text-xs font-semibold text-muted border-t border-navy/8 pt-2">{{ $item->author?->name ?? 'Editorial Team' }} &middot; {{ $item->created_at->timezone($submission->conference->timezone)->format('d M Y H:i') }}</p>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif

                @foreach($submission->uploadAttempts->where('source','author')->where('status','failed') as $attempt)
                    <div class="card border-danger/20 p-4 sm:p-5">
                        <p class="font-bold text-danger break-words">Upload of {{ $attempt->original_name }} failed</p>
                        <p class="mt-1 text-xs text-muted break-words">{{ Str::limit($attempt->error,160) }}</p>
                        <form class="mt-3" method="POST" action="{{ route('author.uploads.retry',[$token,$attempt]) }}">
                            @csrf
                            <button class="btn btn-secondary text-xs w-full sm:w-auto">Retry without selecting file</button>
                        </form>
                    </div>
                @endforeach

                @if (in_array($submission->status, [\App\Enums\SubmissionStatus::NeedsAuthorCorrection, \App\Enums\SubmissionStatus::WaitingAuthorRevision], true))
                    <form method="POST" action="{{ route('author.revision', $token) }}" enctype="multipart/form-data" class="card p-4 sm:p-6" onsubmit="const fileInput = this.querySelector('input[type=file]'); const maxBytes = {{ ($submission->conference->maxFileSizeMb() ?: 25) * 1024 * 1024 }}; if (fileInput && fileInput.files[0] && fileInput.files[0].size > maxBytes) { alert('Ukuran file ' + fileInput.files[0].name + ' (' + (fileInput.files[0].size / (1024*1024)).toFixed(1) + ' MB) melebihi batas maksimal {{ $submission->conference->maxFileSizeMb() ?: 25 }}MB. Silakan pilih file yang lebih kecil.'); fileInput.value = ''; return false; } const btn = this.querySelector('button[type=submit]'); if (btn) { btn.disabled = true; btn.innerHTML = 'Processing...'; }">
                        @csrf
                        <h2 class="text-lg font-black text-navy">Upload Revision</h2>
                        <label class="mt-5 block min-w-0">
                            <span class="form-label">New Editable Source File *</span>
                            <input class="form-input min-w-0 py-3" type="file" name="paper_file" accept=".docx,.zip" required onchange="const maxBytes = {{ ($submission->conference->maxFileSizeMb() ?: 25) * 1024 * 1024 }}; if (this.files[0] && this.files[0].size > maxBytes) { alert('Ukuran file ' + this.files[0].name + ' (' + (this.files[0].size / (1024*1024)).toFixed(1) + ' MB) melebihi batas maksimal {{ $submission->conference->maxFileSizeMb() ?: 25 }}MB.'); this.value = ''; }">
                            <span class="mt-2 block text-xs text-muted">Use DOCX or ZIP containing all LaTeX sources (Max {{ $submission->conference->maxFileSizeMb() ?: 25 }} MB).</span>
                        </label>
                        <label class="mt-5 block min-w-0">
                            <span class="form-label">Revision Notes</span>
                            <textarea class="form-input min-w-0 min-h-24 py-3" name="notes" placeholder="Explain the changes made..."></textarea>
                        </label>
                        <button class="btn btn-primary mt-5 w-full sm:w-auto" type="submit">Submit Revision</button>
                    </form>
                @endif

                <!-- Card File History (Accordion) -->
                <details class="card group overflow-hidden transition">
                    <summary class="flex cursor-pointer items-center justify-between p-4 sm:p-6 list-none font-black text-navy focus:outline-none select-none bg-slate-50/50 hover:bg-slate-100/50 transition">
                        <div class="min-w-0">
                            <h2 class="text-base sm:text-lg font-black text-navy inline-flex items-center gap-2">
                                <span>File Version History</span>
                            </h2>
                            <p class="mt-0.5 text-xs text-muted font-normal">History of uploaded manuscript versions (.docx / .zip).</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="badge badge-primary text-[10px]">{{ $submission->files->count() }} {{ Str::plural('file', $submission->files->count()) }}</span>
                            <span class="rounded-full bg-slate-100 p-2 text-slate-500 group-open:rotate-180 transition-transform">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>
                    </summary>

                    @php
                        $groupedAuthorFiles = $submission->files->groupBy('version_number')->sortByDesc(fn($files, $v) => $v);
                    @endphp
                    <div class="border-t border-navy/8">
                        <!-- Mobile Card View (sm:hidden) -->
                        <div class="divide-y divide-navy/8 sm:hidden">
                            @foreach ($groupedAuthorFiles as $verNum => $filesInVer)
                                @php
                                    $manuscriptFile = $filesInVer->firstWhere('file_category', 'editable_manuscript') ?? $filesInVer->first();
                                    $guidanceFile = $filesInVer->firstWhere('file_category', 'revision_guidance_pdf');
                                @endphp
                                <div class="p-4 space-y-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="badge badge-primary font-mono">v{{ $verNum }}</span>
                                            @if($manuscriptFile->is_final)
                                                <span class="badge badge-success text-[10px] font-bold">Final Version</span>
                                            @endif
                                        </div>
                                        <span class="text-xs font-bold text-muted uppercase tracking-wider">{{ ucfirst($manuscriptFile->source) }}</span>
                                    </div>
                                    <div class="min-w-0 space-y-1">
                                        <p class="text-sm font-bold text-navy break-words">
                                            {{ $manuscriptFile->label }}
                                        </p>
                                        <p class="text-xs text-muted break-all">📄 {{ $manuscriptFile->original_name }}</p>
                                        @if($guidanceFile)
                                            <p class="text-xs text-indigo-900 font-bold break-all">📸 {{ $guidanceFile->original_name }} (Visual Guidance PDF)</p>
                                        @endif
                                        @if($manuscriptFile->notes)
                                            <div class="mt-1.5 rounded-lg bg-amber-50/80 border border-amber-200/80 p-2 text-xs text-amber-900 leading-snug break-words">
                                                <span class="font-bold text-amber-950">📝 Notes:</span> {{ $manuscriptFile->notes }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="pt-1 flex flex-col gap-2">
                                        <a class="btn btn-secondary text-xs w-full py-2.5 flex items-center justify-center gap-2" href="{{ route('author.files.download', [$token, $manuscriptFile]) }}">
                                            <span>📥 Download Manuscript (v{{ $verNum }})</span>
                                        </a>
                                        @if($guidanceFile)
                                            <a class="btn text-xs w-full py-2.5 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition" href="{{ route('author.files.download', [$token, $guidanceFile]) }}">
                                                <span>📸 Download Guidance PDF (v{{ $verNum }})</span>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Desktop Table View (sm:block) -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Version</th>
                                        <th>Manuscript & Guidance Files</th>
                                        <th>Source</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($groupedAuthorFiles as $verNum => $filesInVer)
                                        @php
                                            $manuscriptFile = $filesInVer->firstWhere('file_category', 'editable_manuscript') ?? $filesInVer->first();
                                            $guidanceFile = $filesInVer->firstWhere('file_category', 'revision_guidance_pdf');
                                        @endphp
                                        <tr>
                                            <td class="whitespace-nowrap font-mono font-bold">
                                                v{{ $verNum }}
                                                @if($manuscriptFile->is_final)
                                                    <span class="badge badge-success text-[10px] ml-1.5 font-bold">Final</span>
                                                @endif
                                            </td>
                                            <td class="min-w-[220px]">
                                                <p class="font-bold text-navy break-words">{{ $manuscriptFile->label }}</p>
                                                <p class="text-xs text-muted break-all">📄 {{ $manuscriptFile->original_name }}</p>
                                                @if($guidanceFile)
                                                    <div class="mt-1 flex items-center gap-1.5 text-xs text-indigo-900 font-semibold bg-indigo-50/80 p-1.5 rounded border border-indigo-200/80">
                                                        <span>📸 Guidance PDF:</span>
                                                        <span class="truncate">{{ $guidanceFile->original_name }}</span>
                                                    </div>
                                                @endif
                                                @if($manuscriptFile->notes)
                                                    <div class="mt-1.5 rounded-lg bg-amber-50/80 border border-amber-200/80 p-2 text-xs text-amber-900 leading-snug break-words">
                                                        <span class="font-bold text-amber-950">📝 Notes:</span> {{ $manuscriptFile->notes }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap">{{ ucfirst($manuscriptFile->source) }}</td>
                                            <td class="whitespace-nowrap text-right space-x-3">
                                                <a class="font-bold text-orange hover:underline text-xs" href="{{ route('author.files.download', [$token, $manuscriptFile]) }}">Download Manuscript</a>
                                                @if($guidanceFile)
                                                    <a class="font-bold text-indigo-700 hover:underline text-xs" href="{{ route('author.files.download', [$token, $guidanceFile]) }}">Download Guidance PDF</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            </section>

            <aside class="card h-fit p-4 sm:p-6 min-w-0">
                <h2 class="text-lg font-black text-navy">Timeline</h2>
                <p class="text-xs text-muted mt-0.5">Submission status and milestone progress.</p>

                @php
                    $authorStatusHistory = $submission->statusHistory->reject(function ($history) {
                        return in_array($history->to_status, [
                            \App\Enums\SubmissionStatus::ReviewerReview,
                            \App\Enums\SubmissionStatus::ReviewerChangesRequested,
                        ], true);
                    });
                @endphp

                <ol class="mt-5 space-y-5 border-l-2 border-navy/10 pl-5">
                    @foreach ($authorStatusHistory as $history)
                        <li class="min-w-0">
                            @php
                                $circleColor = match($history->to_status) {
                                    \App\Enums\SubmissionStatus::Done => 'bg-emerald-500 ring-4 ring-emerald-100',
                                    \App\Enums\SubmissionStatus::Withdrawn, \App\Enums\SubmissionStatus::Rejected => 'bg-rose-500 ring-4 ring-rose-100',
                                    \App\Enums\SubmissionStatus::ReadyForEdas => 'bg-sky-500 ring-4 ring-sky-100',
                                    default => 'bg-orange ring-4 ring-warm',
                                };
                            @endphp
                            <span class="-ml-[23px] sm:-ml-[27px] mr-3 inline-block size-3 rounded-full {{ $circleColor }}"></span>
                            <span class="text-sm font-bold text-navy break-words">{{ $history->to_status->label() }}</span>
                            <p class="mt-1 text-xs text-muted">{{ $history->created_at->timezone($submission->conference->timezone)->format('d M Y H:i') }}</p>
                        </li>
                    @endforeach
                </ol>
            </aside>
        </div>
    </div>
</x-layouts.public>
