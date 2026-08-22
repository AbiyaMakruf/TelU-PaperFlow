<x-layouts.public :title="$submission->paper_code">
    <div class="mx-auto max-w-5xl space-y-6 sm:space-y-8">
        <div class="flex items-center justify-between gap-4">
            <p class="eyebrow truncate">Author Portal &middot; {{ $submission->conference->name }}</p>
            <x-status-badge :submission="$submission" class="shrink-0" />
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.4fr_.6fr]">
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
                            $finalVersionFile = $submission->files->firstWhere('is_final', true);
                            if ($finalVersionFile) {
                                $targetVersion = $finalVersionFile->version_number;
                                $isFinalVersion = true;
                            } else {
                                $targetVersion = $submission->files->max('version_number');
                                $isFinalVersion = false;
                            }

                            $filesInTargetVersion = $submission->files->where('version_number', $targetVersion);
                            $targetManuscript = $filesInTargetVersion->firstWhere('file_category', 'editable_manuscript') ?? $filesInTargetVersion->first();
                            $targetGuidancePdf = $filesInTargetVersion->firstWhere('file_category', 'revision_guidance_pdf');
                            $cameraReadyPdf = $submission->files->firstWhere('file_category', 'camera_ready_pdf');
                            $statusLabel = $isFinalVersion ? 'Final' : 'Latest';
                        @endphp
                        @if($targetManuscript || $cameraReadyPdf)
                            <div class="mt-5 pt-5 border-t border-navy/10 space-y-3" style="padding-top: 1.25rem; margin-top: 1.25rem;">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-extrabold text-navy uppercase tracking-wider">
                                        {{ $statusLabel }} Manuscript Files
                                    </span>
                                    <span class="badge {{ $isFinalVersion ? 'badge-success' : 'badge-neutral' }} text-[10px] font-bold">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                                <div class="grid gap-3 {{ $targetGuidancePdf ? 'sm:grid-cols-2' : 'grid-cols-1' }}">
                                    @if($targetManuscript)
                                        <!-- Manuscript Download Card -->
                                        <a href="{{ route('author.files.download', [$token, $targetManuscript]) }}" class="group p-3 sm:p-3.5 rounded-2xl border border-orange/20 bg-amber-50/60 hover:bg-amber-100/90 hover:border-orange/40 transition-all duration-200 flex items-center justify-between gap-2 sm:gap-3 shadow-2xs w-full min-w-0 overflow-hidden cursor-pointer">
                                            <div class="min-w-0 flex-1 space-y-0.5">
                                                <span class="text-[10px] font-extrabold text-orange uppercase tracking-wider block">{{ $statusLabel }} Manuscript</span>
                                                <p class="text-xs font-bold text-navy truncate" title="{{ $targetManuscript->original_name }}">{{ $targetManuscript->original_name }}</p>
                                                <span class="text-[11px] text-muted font-medium block">{{ number_format($targetManuscript->size / 1024, 0) }} KB</span>
                                            </div>
                                            <span class="btn text-[11px] sm:text-xs py-1.5 px-2.5 sm:py-2 sm:px-3.5 bg-orange group-hover:bg-orange-dark text-white font-extrabold shadow-2xs rounded-xl shrink-0 transition-colors duration-200">
                                                Download
                                            </span>
                                        </a>
                                    @endif

                                    @if($targetGuidancePdf)
                                        <!-- Revision Guide Download Card -->
                                        <a href="{{ route('author.files.download', [$token, $targetGuidancePdf]) }}" class="group p-3 sm:p-3.5 rounded-2xl border border-indigo-200/80 bg-indigo-50/60 hover:bg-indigo-100/80 hover:border-indigo-300 transition-all duration-200 flex items-center justify-between gap-2 sm:gap-3 shadow-2xs w-full min-w-0 overflow-hidden cursor-pointer">
                                            <div class="min-w-0 flex-1 space-y-0.5">
                                                <span class="text-[10px] font-extrabold text-indigo-700 uppercase tracking-wider block">Revision Guide</span>
                                                <p class="text-xs font-bold text-indigo-950 truncate" title="{{ $targetGuidancePdf->original_name }}">{{ $targetGuidancePdf->original_name }}</p>
                                                <span class="text-[11px] text-indigo-700 font-medium block">PDF Guide &middot; {{ number_format($targetGuidancePdf->size / 1024, 0) }} KB</span>
                                            </div>
                                            <span class="btn text-[11px] sm:text-xs py-1.5 px-2.5 sm:py-2 sm:px-3.5 bg-indigo-600 group-hover:bg-indigo-700 text-white font-extrabold shadow-2xs rounded-xl shrink-0 transition-colors duration-200">
                                                Download
                                            </span>
                                        </a>
                                    @endif
                                </div>

                                @if($cameraReadyPdf)
                                    <!-- IEEE PDF eXpress Passed Camera-Ready PDF Download Card (RED BUTTON) -->
                                    <a href="{{ route('author.files.download', [$token, $cameraReadyPdf]) }}" class="group p-3 sm:p-3.5 rounded-2xl border border-rose-200/80 bg-rose-50/60 hover:bg-rose-100/80 hover:border-rose-300 transition-all duration-200 flex items-center justify-between gap-2 sm:gap-3 shadow-2xs w-full min-w-0 overflow-hidden mt-3 cursor-pointer">
                                        <div class="min-w-0 flex-1 space-y-0.5">
                                            <span class="text-[10px] font-extrabold text-rose-700 uppercase tracking-wider block">IEEE PDF eXpress Verified (EDAS Camera-Ready PDF)</span>
                                            <p class="text-xs font-bold text-rose-950 truncate" title="{{ $cameraReadyPdf->original_name }}">{{ $cameraReadyPdf->original_name }}</p>
                                            <span class="text-[11px] text-rose-700 font-medium block">Passed IEEE PDF eXpress &amp; Uploaded to EDAS &middot; {{ number_format($cameraReadyPdf->size / 1024, 0) }} KB</span>
                                        </div>
                                        <span class="btn text-[11px] sm:text-xs py-1.5 px-2.5 sm:py-2 sm:px-3.5 bg-rose-600 group-hover:bg-rose-700 text-white font-extrabold shadow-2xs rounded-xl shrink-0 transition-colors duration-200">
                                            Download PDF
                                        </span>
                                    </a>
                                @endif
                            </div>
                        @endif

                        @if($submission->deadline_at && in_array($submission->status, [\App\Enums\SubmissionStatus::NeedsAuthorCorrection, \App\Enums\SubmissionStatus::WaitingAuthorRevision], true))
                            <div class="mt-4 pt-4 border-t border-navy/10 flex flex-wrap items-center justify-between gap-3" style="padding-top: 1rem; margin-top: 1rem;">
                                <div class="min-w-0">
                                    <span class="text-xs font-bold text-muted">Revision Deadline:</span>
                                    <p class="mt-0.5 text-xs font-extrabold {{ $submission->isOverdue() ? 'text-rose-700' : 'text-navy' }}">
                                        {{ $submission->formattedDeadline() }}
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

                <div class="block lg:hidden mt-6">
                    @include('public.partials.editorial-contact')
                </div>

                @if ($submission->status === \App\Enums\SubmissionStatus::WaitingAuthorRevision)
                    <div class="card p-4 sm:p-5 border-amber-300 bg-amber-50/80 text-xs text-amber-950 space-y-2.5 shadow-2xs">
                        <h3 class="font-extrabold text-amber-950 text-sm">Important Revision Instructions</h3>
                        <ul class="list-disc list-inside space-y-1.5 leading-relaxed text-amber-900 font-medium">
                            <li>Please inspect the <strong>Editorial Compliance Checklist Monitoring (Live)</strong> card below to see specific items requiring correction (marked with <strong class="text-rose-700 font-extrabold">✕ Revision Needed</strong>).</li>
                            <li><strong>Always use the Latest Manuscript File</strong> as the base for your revisions, because the editorial team may have already performed initial formatting corrections on it. You can download this file from the <strong>Submission Details</strong> card above (under <em>{{ $statusLabel ?? 'Latest' }} Manuscript Files</em>) or from the <strong>File Version History</strong> section at the bottom of this page.</li>
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
                            $guidelinesUrl = $submission->conference->editorialGuidelinesUrl();
                        @endphp
                        <div class="mt-4 pt-4 border-t border-slate-100 space-y-2">
                            @if($guidelinesUrl)
                                <div class="mb-4 p-3.5 rounded-xl bg-gradient-to-r from-orange/10 via-amber-50 to-orange/5 border border-orange/20 text-xs text-navy flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                                    <div class="flex items-start gap-2.5 min-w-0">
                                        <span class="text-base shrink-0">📖</span>
                                        <div class="min-w-0">
                                            <strong class="font-extrabold text-navy block text-xs">Manual Formatting Check Guidelines</strong>
                                            <span class="text-muted text-[11px] block mt-0.5">To review full manuscript formatting rules and detailed compliance instructions, please open the guidelines document.</span>
                                        </div>
                                    </div>
                                    <a href="{{ $guidelinesUrl }}" target="_blank" rel="noopener" class="btn text-[11px] py-2 px-3.5 bg-orange hover:bg-orange-dark text-white font-extrabold shadow-2xs rounded-xl shrink-0 transition self-start sm:self-auto inline-flex items-center gap-1.5">
                                        <span>Open Formatting Guidelines ↗</span>
                                    </a>
                                </div>
                            @endif

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
                                        <div class="min-w-0 flex-1 space-y-2.5">
                                            <p class="text-xs font-extrabold break-words {{ $titleColor }}">{{ $item->title }}</p>
                                            @if($item->description)
                                                <div class="space-y-0.5">
                                                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 block">Guideline</span>
                                                    <p class="text-[11px] text-slate-600 leading-relaxed break-words">{{ $item->description }}</p>
                                                </div>
                                            @endif
                                            @if(!$res?->is_checked && $res?->note)
                                                <div class="p-2.5 rounded-lg bg-rose-100/80 border border-rose-300/80 text-rose-950 space-y-1 shadow-2xs">
                                                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-800 block">Editor's Revision Note</span>
                                                    <p class="text-[11px] font-semibold text-rose-950 leading-relaxed break-words">{{ $res->note }}</p>
                                                </div>
                                            @elseif($res?->note)
                                                <div class="p-2 rounded-lg bg-slate-100/90 border border-slate-200 text-slate-800 space-y-0.5">
                                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600 block">Editor Note</span>
                                                    <p class="text-[11px] font-medium text-slate-800 leading-relaxed break-words">{{ $res->note }}</p>
                                                </div>
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
                <details class="card group overflow-hidden transition" x-data="{ activeNotesModal: null }">
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
                                        <p class="text-xs text-muted break-all">{{ $manuscriptFile->original_name }}</p>
                                        @if($manuscriptFile->notes)
                                            <button type="button" data-label="{{ $manuscriptFile->label }} (v{{ $verNum }})" data-notes="{{ $manuscriptFile->notes }}" @click="activeNotesModal = { label: $el.dataset.label, text: $el.dataset.notes }" class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 hover:bg-amber-100 border border-amber-300/70 text-[11px] font-bold text-amber-900 transition shadow-2xs">
                                                <svg class="size-3 text-amber-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                                <span>Notes</span>
                                            </button>
                                        @endif
                                    </div>
                                    <div class="pt-1 flex flex-col gap-2">
                                        <a class="btn btn-secondary text-xs w-full py-2.5 flex items-center justify-center gap-2" href="{{ route('author.files.download', [$token, $manuscriptFile]) }}">
                                            <span>Download Manuscript (v{{ $verNum }})</span>
                                        </a>
                                        @if($guidanceFile)
                                            <a class="btn text-xs w-full py-2.5 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition" href="{{ route('author.files.download', [$token, $guidanceFile]) }}">
                                                <span>Download Revision Guide (v{{ $verNum }})</span>
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
                                        <th>File</th>
                                        <th>Source</th>
                                        <th>Download</th>
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
                                                <p class="text-xs text-muted break-all">{{ $manuscriptFile->original_name }}</p>
                                                @if($manuscriptFile->notes)
                                                    <button type="button" data-label="{{ $manuscriptFile->label }} (v{{ $verNum }})" data-notes="{{ $manuscriptFile->notes }}" @click="activeNotesModal = { label: $el.dataset.label, text: $el.dataset.notes }" class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 hover:bg-amber-100 border border-amber-300/70 text-[11px] font-bold text-amber-900 transition shadow-2xs">
                                                        <svg class="size-3 text-amber-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                        </svg>
                                                        <span>Notes</span>
                                                    </button>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap">{{ ucfirst($manuscriptFile->source) }}</td>
                                            <td class="whitespace-nowrap">
                                                <div class="flex flex-col items-start gap-1 py-1">
                                                    <a class="btn text-xs px-2.5 py-1 font-bold bg-orange hover:bg-orange-dark text-white shadow-2xs transition w-full text-center" href="{{ route('author.files.download', [$token, $manuscriptFile]) }}">
                                                        Manuscript
                                                    </a>
                                                    @if($guidanceFile)
                                                        <a class="btn text-xs px-2.5 py-1 font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-2xs transition w-full text-center" href="{{ route('author.files.download', [$token, $guidanceFile]) }}">
                                                            Revision Guide
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- File Notes Modal -->
                    <template x-teleport="body">
                        <div x-show="activeNotesModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" @keydown.escape.window="activeNotesModal = null">
                            <div class="card max-w-lg w-full p-5 sm:p-6 space-y-4 bg-white shadow-xl rounded-2xl border border-slate-200 min-w-0" @click.away="activeNotesModal = null">
                                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3 min-w-0">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="size-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold shrink-0">
                                            <svg class="size-5 text-amber-800" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="font-black text-navy text-sm sm:text-base leading-tight truncate" x-text="activeNotesModal?.label || 'File Notes'"></h3>
                                            <p class="text-xs text-muted">Revision notes &amp; file description</p>
                                        </div>
                                    </div>
                                    <button type="button" @click="activeNotesModal = null" class="text-muted hover:text-navy font-bold text-lg p-1 shrink-0">
                                        &times;
                                    </button>
                                </div>
                                <div class="bg-amber-50/60 p-4 rounded-xl border border-amber-200/70 text-xs sm:text-sm text-slate-800 leading-relaxed whitespace-pre-line break-words max-h-[60vh] overflow-y-auto" x-text="activeNotesModal?.text">
                                </div>
                                <div class="flex justify-end pt-1">
                                    <button type="button" @click="activeNotesModal = null" class="btn btn-secondary text-xs px-4 py-2 font-bold">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </details>
            </section>

            <aside class="space-y-6 min-w-0">
                <!-- Editorial Contact Information Card (Desktop) -->
                <div class="hidden lg:block">
                    @include('public.partials.editorial-contact')
                </div>

                <!-- Timeline Card -->
                <div class="card p-4 sm:p-6 min-w-0">
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
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
