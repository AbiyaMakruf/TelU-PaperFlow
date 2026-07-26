<x-layouts.app :title="$submission->paper_code" heading="Paper Details">
    <a class="back-link" href="{{ route('submissions.index') }}">&larr; Back to papers</a>
    <div class="mt-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-start min-w-0">
        <div class="min-w-0">
            <p class="eyebrow truncate">{{ $submission->conference->name }} &middot; internal code {{ $submission->paper_code }}</p>
            <h1 class="page-title leading-tight break-words">{{ $submission->paper_id ?: $submission->paper_code }}</h1>
            <p class="page-subtitle leading-snug break-words max-w-full">{{ $submission->title }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5 shrink-0 self-start sm:self-auto">
            @php
                $portalToken = $submission->ensureValidAuthorToken();
                $latestFile = $submission->files->first();
                $editorialCycle = $submission->reviewCycles()->where('stage', \App\Enums\ReviewStage::Editorial)->where('status', 'open')->first();
                $allEditorialPassed = false;
                if ($editorialCycle && $editorialCycle->template) {
                    $requiredItemIds = $editorialCycle->template->items->where('is_required', true)->pluck('id');
                    $checkedItemIds = $editorialCycle->results->where('is_checked', true)->pluck('checklist_item_id');
                    $allEditorialPassed = $requiredItemIds->isNotEmpty() && $requiredItemIds->diff($checkedItemIds)->isEmpty();
                }
            @endphp
            <x-status-badge :status="$submission->status" />
        </div>
    </div>

    @if($submission->is_flagged_duplicate)
        <div class="mt-6 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800 break-words max-w-full">
            <p class="font-extrabold text-rose-900">⚠️ Potential Duplicate Submission Warning</p>
            <p class="mt-1">{{ $submission->duplicate_notes }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="mt-6 rounded-xl border border-danger/20 bg-danger/8 p-4 text-sm text-danger max-w-full">
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li class="break-words">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-7 grid gap-6 xl:grid-cols-[1fr_340px] min-w-0 w-full max-w-full">
        <div class="space-y-6 min-w-0 w-full max-w-full">
            <!-- Data Submission Card -->
            <section class="card p-4 sm:p-6 max-w-full min-w-0">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between border-b border-navy/8 pb-3">
                    <h2 class="text-base sm:text-lg font-black text-navy">Submission Details</h2>
                    <span class="text-xs text-muted font-medium">{{ $submission->submitted_at?->timezone($submission->conference->timezone)->format('d M Y H:i') }}</span>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2 text-xs sm:text-sm">
                    <div class="min-w-0">
                        <dt class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-muted">Primary Author</dt>
                        <dd class="mt-1 font-bold text-navy leading-snug break-words">{{ $submission->corresponding_author_name }}</dd>
                        <dd class="text-xs text-muted break-all">{{ $submission->corresponding_author_email }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-muted">Phone Number</dt>
                        <dd class="mt-1 font-medium text-navy break-all">{{ $submission->corresponding_author_phone ?: '-' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-muted">Editable Format</dt>
                        <dd class="mt-1 font-medium text-navy">{{ $submission->manuscript_format === 'latex' ? 'LaTeX (ZIP)' : ($submission->manuscript_format === 'docx' ? 'Microsoft Word (DOCX)' : 'Not confirmed') }}</dd>
                    </div>
                    @foreach($submission->formVersion?->schema ?? [] as $field)
                        @continue(in_array($field['key'], ['co_authors', 'affiliation', 'country']))
                        <div class="min-w-0">
                            <dt class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-muted">{{ $field['label'] }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-navy break-words leading-relaxed">{{ $submission->answers[$field['key']] ?? '-' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-5 border-t border-navy/10 pt-4 flex flex-wrap items-center gap-3">
                    @if($latestFile)
                        <a href="{{ route('submissions.files.download', [$submission, $latestFile]) }}" class="btn text-xs py-2 px-4 inline-flex items-center gap-1.5 font-black shadow-xs bg-orange hover:bg-orange-dark text-white rounded-xl transition" title="Download latest manuscript version (v{{ $latestFile->version_number }} - {{ $latestFile->original_name }})">
                            Download Latest File (v{{ $latestFile->version_number }})
                        </a>
                    @endif
                    <a href="{{ route('author.portal', ['token' => $portalToken]) }}" target="_blank" rel="noopener" class="btn btn-secondary text-xs py-2 px-4 inline-flex items-center gap-1.5 shadow-xs hover:border-orange hover:text-orange rounded-xl transition" title="Inspect author portal view exactly as seen by the author">
                        Open Author Portal ↗
                    </a>
                </div>

                @if($submission->authors->count() > 1)
                    <div class="mt-5 border-t border-navy/10 pt-5">
                        <h3 class="font-bold text-navy text-sm">Co-Authors</h3>
                        <div class="mt-3 grid gap-3 grid-cols-1 sm:grid-cols-2">
                            @foreach($submission->authors->where('is_corresponding', false) as $author)
                                <div class="rounded-xl bg-warm/80 p-3.5 border border-navy/8 text-xs min-w-0">
                                    <p class="font-bold text-navy break-words">{{ $author->name }}</p>
                                    <p class="text-[11px] text-muted mt-0.5 break-all">
                                        {{ $author->email ?: '-' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @can('assign', $submission)
                    @if($submission->status === \App\Enums\SubmissionStatus::Submitted)
                        <div class="mt-6 grid gap-4 border-t border-navy/10 pt-6 grid-cols-1 md:grid-cols-2">
                            <form method="POST" action="{{ route('submissions.accept', $submission) }}">
                                @csrf
                                <button class="btn btn-primary w-full text-xs py-2.5">Data Valid &amp; Proceed to Assignment</button>
                            </form>
                            <form method="POST" action="{{ route('submissions.correction', $submission) }}" class="space-y-3">
                                @csrf
                                <textarea class="form-input min-h-24 py-3 text-xs" name="feedback" placeholder="Describe the data needing correction..." required></textarea>
                                <button class="btn btn-secondary w-full text-xs py-2.5">Return to Author</button>
                            </form>
                        </div>
                    @endif
                @endcan
            </section>

            <!-- Checklist Sections -->
            @foreach([\App\Enums\ReviewStage::Editorial] as $stage)
                @php
                    $allowed = auth()->user()->can('editorialReview', $submission);
                    $template = $submission->conference->checklistTemplates->where('stage', $stage)->where('is_active', true)->first();
                    $cycle = $submission->reviewCycles->where('stage', $stage)->where('status', 'open')->first() ?? $submission->reviewCycles->where('stage', $stage)->first();
                    $requiredItemIds = $template ? $template->items->where('is_required', true)->pluck('id') : collect();
                    $initialPassedCount = $template ? $template->items->where('is_required', true)->filter(fn($i) => (bool) $cycle?->results->firstWhere('checklist_item_id', $i->id)?->is_checked)->count() : 0;
                    $isEditorialActive = ($submission->status === \App\Enums\SubmissionStatus::EditorialReview);
                    $isReviewerActive = in_array($submission->status, [\App\Enums\SubmissionStatus::ReviewerReview, \App\Enums\SubmissionStatus::EdasFixRequired, \App\Enums\SubmissionStatus::ReadyForEdas], true);
                @endphp
                @if($allowed && $template)
                    <div x-data="{
                        requiredIds: @js($requiredItemIds->values()->all()),
                        hasReviewer: {{ json_encode((bool)$submission->reviewer_id) }},
                        checkedCount: {{ $initialPassedCount }},
                        savingChecklist: false,
                        autoSaveStatus: '',
                        isEditorialActive: {{ json_encode($isEditorialActive) }},
                        get allPassed() {
                            return this.requiredIds.length > 0 && this.checkedCount >= this.requiredIds.length;
                        },
                        updateCheckedState() {
                            if (!this.isEditorialActive) return;
                            const form = document.getElementById('checklist-form-{{ $stage->value }}');
                            if (!form) return;
                            let count = 0;
                            this.requiredIds.forEach(id => {
                                const checkRadio = form.querySelector(`.radio-check-input[name='items[${id}][checked]']`);
                                if (checkRadio && checkRadio.checked) {
                                    count++;
                                }
                            });
                            this.checkedCount = count;
                            this.autoSaveChecklist();
                        },
                        async autoSaveChecklist() {
                            if (!this.isEditorialActive) return;
                            const form = document.getElementById('checklist-form-{{ $stage->value }}');
                            if (!form) return;
                            this.savingChecklist = true;
                            this.autoSaveStatus = '';
                            try {
                                const formData = new FormData(form);
                                const response = await fetch(form.action, {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json'
                                    }
                                });
                                if (response.ok) {
                                    this.autoSaveStatus = 'Auto-saved ✓';
                                    setTimeout(() => { if (this.autoSaveStatus === 'Auto-saved ✓') this.autoSaveStatus = ''; }, 2500);
                                }
                            } catch (e) {
                                console.error('Checklist auto-save error:', e);
                                this.autoSaveStatus = '';
                            } finally {
                                this.savingChecklist = false;
                            }
                        },
                        async prepareFeedbackSubmit() {
                            if (!this.isEditorialActive) return;
                            const form = document.getElementById('checklist-form-{{ $stage->value }}');
                            if (form) {
                                this.autoSaveStatus = '';
                                try {
                                    await fetch(form.action, {
                                        method: 'POST',
                                        body: new FormData(form),
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'Accept': 'application/json'
                                        }
                                    });
                                } catch (e) {
                                    console.error(e);
                                }
                            }
                        }
                    }" class="space-y-6">
                        <details class="card overflow-hidden max-w-full min-w-0" @if($submission->status === \App\Enums\SubmissionStatus::EditorialReview) open @endif>
                            <summary class="cursor-pointer list-none p-4 sm:p-6 text-base sm:text-lg font-black text-navy flex items-center justify-between select-none">
                                <span>Editorial Compliance Checklist (16 IEEE Rules)</span>
                                <span class="text-orange font-bold text-xl">+</span>
                            </summary>
                            <form method="POST" action="{{ route('submissions.checklist', [$submission, $stage->value]) }}" class="space-y-4 border-t border-navy/10 p-4 sm:p-6" id="checklist-form-{{ $stage->value }}">
                                @csrf
                                @method('PUT')

                                @php
                                    $previousCycle = $submission->reviewCycles
                                        ->where('stage', $stage)
                                        ->reject(fn($c) => $c->id === $cycle?->id)
                                        ->sortByDesc('cycle_number')
                                        ->first();
                                    $hasBeforeColumn = (bool) $previousCycle;
                                @endphp

                                @if(!$isEditorialActive)
                                    <div class="rounded-xl bg-amber-50 p-3.5 border border-amber-200 text-xs text-amber-900 flex items-center gap-2 font-bold select-none">
                                        <span class="text-base shrink-0">ℹ️</span>
                                        <span>Editorial checklist is in <strong>Read Only</strong> mode because current paper status is <strong>{{ $submission->status->label() }}</strong>. Checklist can only be modified during Editorial Compliance Check.</span>
                                    </div>
                                @endif

                                <!-- Quick Batch Action Buttons (Check All / Uncheck All) -->
                                <div class="flex flex-wrap items-center justify-between gap-2.5 bg-slate-50 p-3 rounded-xl border border-navy/10">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-navy">Quick Batch Actions:</span>
                                        @if($cycle)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-300">
                                                <span>ℹ️</span> Author Revision (Cycle #{{ $cycle?->cycle_number }})
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @if(!$isEditorialActive) disabled @endif @click="
                                            if (!isEditorialActive) return;
                                            const form = document.getElementById('checklist-form-{{ $stage->value }}');
                                            if (!form) return;
                                            form.querySelectorAll('tbody tr').forEach(tr => {
                                                const radio = tr.querySelector('.radio-check-input');
                                                if (radio) radio.checked = true;
                                                if (tr._x_dataStack && tr._x_dataStack[0]) tr._x_dataStack[0].checked = true;
                                            });
                                            updateCheckedState(true);
                                        " class="btn text-xs py-1.5 px-3 bg-emerald-50 text-emerald-800 border border-emerald-300 hover:bg-emerald-100 font-extrabold shadow-2xs transition disabled:opacity-50 disabled:cursor-not-allowed">
                                            ✓ Check All Passed
                                        </button>
                                        <button type="button" @if(!$isEditorialActive) disabled @endif @click="
                                            if (!isEditorialActive) return;
                                            const form = document.getElementById('checklist-form-{{ $stage->value }}');
                                            if (!form) return;
                                            form.querySelectorAll('tbody tr').forEach(tr => {
                                                const radio = tr.querySelector('.radio-cross-input');
                                                if (radio) radio.checked = true;
                                                if (tr._x_dataStack && tr._x_dataStack[0]) tr._x_dataStack[0].checked = false;
                                            });
                                            updateCheckedState(true);
                                        " class="btn text-xs py-1.5 px-3 bg-rose-50 text-rose-800 border border-rose-300 hover:bg-rose-100 font-extrabold shadow-2xs transition disabled:opacity-50 disabled:cursor-not-allowed">
                                            ✕ Uncheck All
                                        </button>
                                    </div>
                                </div>

                                <!-- Data Table -->
                                <div class="overflow-x-auto min-w-0 max-w-full rounded-xl border border-navy/10">
                                    <table class="data-table min-w-[720px]">
                                        <thead>
                                            <tr>
                                                <th class="p-3.5 sm:p-4 font-black">Checklists</th>
                                                <th class="p-3.5 sm:p-4 text-center w-28 font-black">Completed</th>
                                                @if($hasBeforeColumn)
                                                    <th class="p-3.5 sm:p-4 text-center w-20 font-black bg-navy/80 border-l border-white/10" title="Status in previous review cycle before author revision">Before</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-navy/10 bg-white">
                                            @foreach($template->items as $index => $item)
                                                @php
                                                    $result = $cycle?->results->firstWhere('checklist_item_id', $item->id);
                                                    $prevResult = $previousCycle?->results->firstWhere('checklist_item_id', $item->id);
                                                    $hasNote = !empty($result?->note);
                                                @endphp
                                                <tr class="hover:bg-slate-50/70 transition" x-data="{ openGuidance: false, openNote: {{ json_encode($hasNote) }}, checked: {{ json_encode((bool)($result?->is_checked)) }} }">
                                                    <td class="p-3.5 sm:p-4 align-top min-w-0">
                                                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2 min-w-0">
                                                            <div class="min-w-0">
                                                                <strong class="text-navy text-xs sm:text-sm font-extrabold leading-snug break-words block">
                                                                    {{ $item->title }} @if($item->is_required)<span class="text-orange">*</span>@endif
                                                                </strong>
                                                            </div>
                                                            <div class="flex items-center gap-2.5 shrink-0 self-start">
                                                                @if($item->description)
                                                                    <button type="button" @click="openGuidance = !openGuidance" class="text-xs font-bold text-orange hover:underline">
                                                                        <span x-text="openGuidance ? 'Close Guidance −' : 'Guidance Details +'"></span>
                                                                    </button>
                                                                @endif
                                                                <button type="button" @click="openNote = !openNote" class="text-xs font-bold text-navy hover:underline">
                                                                    <span x-text="openNote ? 'Hide Note −' : '+ Add Note'"></span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        @if($item->description)
                                                            <div x-show="openGuidance" x-cloak class="mt-2.5 rounded-lg bg-amber-50/70 p-3 text-xs leading-relaxed text-slate-700 border border-amber-200/60 break-words">
                                                                <strong class="block text-navy font-bold mb-1">💡 Guidance / Inspection Details:</strong>
                                                                <p class="leading-relaxed whitespace-pre-line">{{ $item->description }}</p>
                                                            </div>
                                                        @endif
                                                        <div x-show="openNote" x-cloak class="mt-2.5">
                                                            <textarea class="form-input min-h-14 py-2 text-xs item-note-input" name="items[{{ $item->id }}][note]" @if(!$isEditorialActive) disabled @endif @change="autoSaveChecklist()" @blur="autoSaveChecklist()" placeholder="Specific item note (e.g. Abstract is only 120 words)...">{{ $result?->note }}</textarea>
                                                        </div>
                                                    </td>
                                                    <td class="p-3.5 sm:p-4 align-top text-center">
                                                        <div class="inline-flex items-center gap-1 rounded-xl bg-slate-100 p-1 border border-navy/10 shadow-inner">
                                                            <label class="cursor-pointer inline-flex items-center justify-center size-8 rounded-lg font-black text-xs transition border select-none"
                                                                   :class="!checked ? 'bg-rose-600 text-white border-rose-700 shadow-sm' : 'text-slate-400 border-transparent hover:bg-rose-100 hover:text-rose-600'"
                                                                   title="Unchecked / Reject">
                                                                <input type="radio" name="items[{{ $item->id }}][checked]" value="0" :checked="!checked" :disabled="!isEditorialActive" @if(!$isEditorialActive) disabled @endif @change="checked = false; updateCheckedState()" class="sr-only radio-cross-input">
                                                                <span>✕</span>
                                                            </label>
                                                            <label class="cursor-pointer inline-flex items-center justify-center size-8 rounded-lg font-black text-xs transition border select-none"
                                                                :class="checked ? 'bg-emerald-600 text-white border-emerald-700 shadow-sm' : 'text-slate-400 border-transparent hover:bg-emerald-100 hover:text-emerald-600'"
                                                                title="Checked / Complete">
                                                                <input type="radio" name="items[{{ $item->id }}][checked]" value="1" :checked="checked" :disabled="!isEditorialActive" @if(!$isEditorialActive) disabled @endif @change="checked = true; updateCheckedState()" class="sr-only radio-check-input" data-title="{{ e($item->title) }}" data-guidance="{{ e($item->description ?? $item->guidance) }}">
                                                                <span>✓</span>
                                                            </label>
                                                        </div>
                                                    </td>
                                                    @if($hasBeforeColumn)
                                                        <td class="p-3.5 sm:p-4 align-top text-center bg-slate-50/50 border-l border-navy/10">
                                                            @if($prevResult !== null)
                                                                @if($prevResult->is_checked)
                                                                    <span class="inline-flex items-center justify-center size-8 rounded-lg bg-emerald-100 text-emerald-800 border border-emerald-300 font-black text-xs shadow-xs" title="Passed in previous cycle before revision">
                                                                        ✓
                                                                    </span>
                                                                @else
                                                                    <span class="inline-flex items-center justify-center size-8 rounded-lg bg-rose-100 text-rose-800 border border-rose-300 font-black text-xs shadow-xs" title="Unchecked / Rejected in previous cycle before revision">
                                                                        ✕
                                                                    </span>
                                                                @endif
                                                            @else
                                                                <span class="text-slate-400 font-bold text-xs" title="Not evaluated in previous cycle">-</span>
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex items-center justify-between pt-3 border-t border-navy/10">
                                    <span class="text-xs font-bold text-emerald-700" x-text="autoSaveStatus"></span>
                                    <button type="submit" @if(!$isEditorialActive) disabled @endif class="btn btn-primary px-5 py-2 text-xs font-extrabold w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span x-show="!savingChecklist">Save Checklist</span>
                                        <span x-show="savingChecklist" x-cloak style="display:none;">Saving...</span>
                                    </button>
                                </div>
                            </form>

                            <!-- Author Feedback & Action Submission Section (Merged into Editorial Checklist Card) -->
                            <form method="POST" action="{{ route('submissions.feedback', $submission) }}" class="p-4 sm:p-6 border-t border-navy/10 space-y-4 bg-slate-50/50" id="author-feedback-form">
                                @csrf
                                <input type="hidden" name="visibility" value="author">

                                @if(!$isEditorialActive)
                                    <div class="rounded-xl bg-amber-50 p-3.5 border border-amber-200 text-xs text-amber-900 flex items-center gap-2 font-bold select-none">
                                        <span class="text-base shrink-0">ℹ️</span>
                                        <span>Author communication &amp; action buttons are in <strong>Read Only</strong> mode because current paper status is <strong>{{ $submission->status->label() }}</strong>.</span>
                                    </div>
                                @endif

                                <div>
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-1.5">
                                        <label class="form-label text-xs mb-0">
                                            <span x-show="!allPassed">Revision Feedback / Message for Author <span class="text-rose-500">*</span></span>
                                            <span x-show="allPassed" x-cloak style="display: none;">Reviewer Notes (Optional)</span>
                                        </label>
                                        <button type="button" x-show="!allPassed" @if(!$isEditorialActive) disabled @endif @click="generateRevisionFeedback('{{ $stage->value }}', {{ json_encode($isEditorialActive) }})" class="btn text-xs py-1.5 px-3 bg-amber-50 text-amber-900 border border-amber-300 hover:bg-amber-100 font-extrabold shadow-sm transition shrink-0 disabled:opacity-50 disabled:cursor-not-allowed">
                                            ⚡ Use Revision Template (Unchecked Items Only)
                                        </button>
                                    </div>
                                    <textarea class="form-input min-h-28 py-2.5 text-xs font-mono" name="body" id="author-feedback-textarea" :placeholder="!allPassed ? 'Write revision feedback for author...' : 'Write notes or instructions for Reviewer...'" @if(!$isEditorialActive) disabled @endif :required="!allPassed"></textarea>
                                </div>

                                <!-- Revision Deadline Counter (Days) -->
                                <div x-show="!allPassed" class="p-3 bg-amber-50/80 rounded-xl border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <label class="form-label text-xs mb-0 text-amber-950 font-extrabold flex items-center gap-1.5">
                                            <span>📅</span> Revision Deadline Counter (Days)
                                        </label>
                                        <p class="text-[11px] text-amber-800 mt-0.5">Calculated automatically from today until 23:59 GMT+7 (Asia/Jakarta).</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <select name="revision_days" @if(!$isEditorialActive) disabled @endif class="form-input text-xs font-extrabold py-1.5 px-3 bg-white border border-amber-300 rounded-lg text-amber-950 focus:ring-amber-500 shadow-2xs">
                                            <option value="3">3 Days</option>
                                            <option value="5">5 Days</option>
                                            <option value="7" selected>7 Days (Default)</option>
                                            <option value="10">10 Days</option>
                                            <option value="14">14 Days</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Interactive CC Tag Input -->
                                <div x-data="{
                                    ccInput: '',
                                    tags: @js(old('cc') ? array_values(array_filter(preg_split('/[,;\s]+/', old('cc')))) : $defaultCc),
                                    addTag() {
                                        if (!{{ json_encode($isEditorialActive) }}) return;
                                        let val = this.ccInput.trim().replace(/,$/, '');
                                        if (val && !this.tags.includes(val)) {
                                            this.tags.push(val);
                                        }
                                        this.ccInput = '';
                                    },
                                    removeTag(index) {
                                        if (!{{ json_encode($isEditorialActive) }}) return;
                                        this.tags.splice(index, 1);
                                    }
                                }" class="min-w-0">
                                    <label class="form-label text-xs mb-1 block">CC Email (Type email address and press comma / Enter)</label>
                                    <input type="hidden" name="cc" :value="tags.join(',')">
                                    <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-navy/20 bg-white p-2 min-h-11 focus-within:ring-2 focus-within:ring-orange max-w-full">
                                        <template x-for="(tag, index) in tags" :key="index">
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-navy text-white px-2 py-0.5 text-xs font-bold shadow-sm max-w-full truncate">
                                                <span x-text="tag" class="truncate"></span>
                                                <button type="button" @if(!$isEditorialActive) disabled @endif @click="removeTag(index)" class="text-orange hover:text-white font-black text-sm leading-none ml-0.5 shrink-0 disabled:opacity-50">&times;</button>
                                            </span>
                                        </template>
                                        <input class="flex-1 bg-transparent text-xs border-0 focus:outline-none focus:ring-0 p-1 min-w-[120px]"
                                               x-model="ccInput"
                                               @if(!$isEditorialActive) disabled @endif
                                               @keydown.comma.prevent="addTag()"
                                               @keydown.enter.prevent="addTag()"
                                               @blur="addTag()"
                                               placeholder="Type CC email...">
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2.5 pt-3 border-t border-navy/10">
                                    <template x-if="!allPassed">
                                        <button type="submit" name="action" value="request_revision" @click="await prepareFeedbackSubmit()" @if(!$isEditorialActive) disabled @endif class="btn bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 text-xs font-extrabold w-full sm:w-auto shadow-sm flex items-center justify-center gap-1.5 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                            Request Author Revision &amp; Send Email Notification
                                        </button>
                                    </template>

                                    <template x-if="allPassed">
                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full sm:w-auto">
                                            <template x-if="hasReviewer">
                                                <button type="submit" name="action" value="approve_and_send_reviewer" @click="await prepareFeedbackSubmit()" @if(!$isEditorialActive) disabled @endif class="btn bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 text-xs font-extrabold w-full sm:w-auto shadow-sm flex items-center justify-center gap-1.5 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                                    ✓ Approve &amp; Send to Reviewer
                                                </button>
                                            </template>
                                            <template x-if="!hasReviewer">
                                                <button type="button" disabled title="Assign Reviewer PIC in sidebar before sending" class="btn bg-slate-200 text-slate-500 border border-slate-300 cursor-not-allowed px-5 py-2.5 text-xs font-extrabold w-full sm:w-auto flex items-center justify-center gap-1.5 select-none opacity-90">
                                                    ✓ Approve &amp; Send to Reviewer (Assign Reviewer First)
                                                </button>
                                            </template>
                                        </div>
                                    </template>

                                    @if($whatsappUrl)
                                        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn px-4 py-2.5 text-xs font-extrabold bg-[#25D366] text-white hover:bg-[#1faa52] flex items-center justify-center gap-1.5 w-full sm:w-auto text-center shadow-xs">
                                            <svg class="size-4 shrink-0 fill-current" viewBox="0 0 24 24">
                                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                            </svg>
                                            <span>Send via WhatsApp</span>
                                        </a>
                                    @endif
                                </div>
                                <template x-if="allPassed && !hasReviewer">
                                    <p class="text-[11px] font-bold text-amber-700 text-right mt-2">
                                        ⚠️ Reviewer PIC has not been assigned yet. Please select &amp; save a Reviewer PIC in the sidebar.
                                    </p>
                                </template>
                            </form>
                        </details>
                    </div>
                @endif
            @endforeach

                <!-- 2. Confidential Internal Notes (Accordion) -->
                <details class="card overflow-hidden max-w-full min-w-0" id="internal-notes-accordion">
                    <summary class="flex cursor-pointer items-center justify-between p-4 sm:p-5 font-black text-navy bg-slate-50 hover:bg-slate-100 transition select-none border-b border-navy/8">
                        <div class="min-w-0">
                            <h2 class="text-sm sm:text-base font-black text-navy">Internal Notes (Team Only)</h2>
                            <p class="text-[11px] text-muted font-normal truncate">Confidential notes for editorial team &amp; reviewers (hidden from author).</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="badge badge-primary text-[10px]">Confidential</span>
                            <span class="text-xs text-muted">▼</span>
                        </div>
                    </summary>
                    <div class="p-4 sm:p-6 border-t border-navy/8 space-y-4 bg-white">
                        <!-- Internal Notes History -->
                        <div class="space-y-3">
                            @forelse($submission->feedback->where('visibility', 'internal') as $feedback)
                                <div class="rounded-xl bg-slate-50 p-3.5 border border-navy/10 shadow-sm text-xs min-w-0">
                                    <div class="flex items-center justify-between gap-2 text-muted min-w-0">
                                        <span class="font-bold text-navy truncate">{{ $feedback->author?->name ?? 'Staff Member' }}</span>
                                        <span class="text-[11px] shrink-0">{{ $feedback->created_at->format('d M Y H:i') }}</span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line text-slate-800 leading-relaxed break-words">{{ $feedback->body }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-muted italic">No internal notes yet.</p>
                            @endforelse
                        </div>

                        <!-- Add Internal Note Form -->
                        <form method="POST" action="{{ route('submissions.feedback', $submission) }}" class="pt-2 border-t border-navy/8 space-y-3">
                            @csrf
                            <input type="hidden" name="visibility" value="internal">
                            <textarea class="form-input min-h-20 py-2.5 text-xs" name="body" placeholder="Write internal note (visible only to editorial team & reviewers)..." required></textarea>
                            <div class="flex justify-end">
                                <button type="submit" class="btn btn-primary px-4 py-2 text-xs font-extrabold w-full sm:w-auto">
                                    Save Internal Note
                                </button>
                            </div>
                        </form>
                    </div>
                </details>

            <!-- IEEE PDF eXpress & EDAS Section (Accordion) -->
            <details class="card overflow-hidden max-w-full min-w-0" id="pdfexpress-edas-accordion">
                <summary class="flex cursor-pointer items-center justify-between p-4 sm:p-5 font-black text-navy bg-slate-50 hover:bg-slate-100 transition select-none border-b border-navy/8">
                    <div class="min-w-0">
                        <h2 class="text-sm sm:text-base font-black text-navy">IEEE PDF eXpress &amp; EDAS Management</h2>
                        <p class="text-[11px] text-muted font-normal truncate">Reviewer PDF compliance status and EDAS upload error details.</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        @if(($submission->pdf_express_status ?? '') === 'passed')
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black text-emerald-800 border border-emerald-300">
                                PDF eXpress: Passed
                            </span>
                        @elseif(($submission->pdf_express_status ?? '') === 'failed')
                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black text-rose-800 border border-rose-300">
                                PDF eXpress: Failed
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black text-amber-800 border border-amber-300">
                                PDF eXpress: Pending
                            </span>
                        @endif
                        <span class="text-xs text-muted">▼</span>
                    </div>
                </summary>

                <div class="p-4 sm:p-6 bg-white space-y-4">
                    @can('reviewerReview', $submission)
                        @if(!$isReviewerActive)
                            <div class="rounded-xl bg-amber-50 p-3.5 border border-amber-200 text-xs text-amber-900 flex items-center gap-2 font-bold select-none">
                                <span class="text-base shrink-0">ℹ️</span>
                                <span>PDF eXpress &amp; EDAS settings are in <strong>Read Only</strong> mode because current paper status is <strong>{{ $submission->status->label() }}</strong>.</span>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('submissions.edas-status', $submission) }}" class="space-y-4 min-w-0" x-data="{
                            statusState: @js(old('pdf_express_status', $submission->pdf_express_status ?? 'pending')),
                            setError(msg) {
                                let current = $refs.noteInput.value;
                                $refs.noteInput.value = current ? current + '\n' + msg : msg;
                            }
                        }">
                            @csrf
                            <div>
                                <label class="form-label text-xs">IEEE PDF eXpress / EDAS Upload Status *</label>
                                <select class="form-input text-xs font-bold" name="pdf_express_status" x-model="statusState" @if(!$isReviewerActive || $submission->status->isTerminal()) disabled @endif>
                                    <option value="pending">⏳ Pending Verification</option>
                                    <option value="passed">✓ Passed &amp; EDAS Uploaded Successfully</option>
                                    <option value="failed">✕ Failed / EDAS Error Encountered</option>
                                </select>
                            </div>

                            <div x-show="statusState === 'failed'" x-cloak style="display: none;">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="form-label text-xs text-rose-800 font-extrabold">EDAS Error Notes (Reviewer Only) *</label>
                                </div>
                                @if($isReviewerActive && !$submission->status->isTerminal())
                                    <div class="flex flex-wrap gap-1.5 mb-2">
                                        <button type="button" @click="setError('pagesize: The page size is US letter size (8.5 by 11 inches), but only A4 size (210 x 297 mm) is allowed.')" class="text-[10px] bg-rose-50 border border-rose-200 text-rose-800 px-2 py-1 rounded-lg hover:bg-rose-100 font-medium text-left leading-snug break-words shadow-2xs">+ Page Size US Letter</button>
                                        <button type="button" @click="setError('The final manuscript must have at least 5 filled pages, not just 4.')" class="text-[10px] bg-rose-50 border border-rose-200 text-rose-800 px-2 py-1 rounded-lg hover:bg-rose-100 font-medium text-left leading-snug break-words shadow-2xs">+ Min 5 Pages</button>
                                        <button type="button" @click="setError('authorname: Doubleblind conference, but author names are visible on the first page.')" class="text-[10px] bg-rose-50 border border-rose-200 text-rose-800 px-2 py-1 rounded-lg hover:bg-rose-100 font-medium text-left leading-snug break-words shadow-2xs">+ Doubleblind Author Visible</button>
                                        <button type="button" @click="setError('Authors must first upload or fill out the IEEE copyright form.')" class="text-[10px] bg-rose-50 border border-rose-200 text-rose-800 px-2 py-1 rounded-lg hover:bg-rose-100 font-medium text-left leading-snug break-words shadow-2xs">+ IEEE Copyright Missing</button>
                                    </div>
                                @endif
                                <textarea x-ref="noteInput" class="form-input text-xs min-h-20" name="edas_error_note" placeholder="Write EDAS error details or click preset buttons above..." @if(!$isReviewerActive || $submission->status->isTerminal()) disabled @endif>{{ old('edas_error_note', $submission->edas_error_note) }}</textarea>
                            </div>

                            @if($isReviewerActive && !$submission->status->isTerminal())
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2.5 pt-2 border-t border-navy/10">
                                    <button type="submit" name="action" value="save_status" class="btn btn-secondary px-4 py-2 text-xs font-bold w-full sm:w-auto" x-show="statusState === 'pending'">
                                        Save Reviewer Notes
                                    </button>

                                    <button type="submit" name="action" value="reviewer_changes" class="btn bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 text-xs font-extrabold w-full sm:w-auto shadow-sm flex items-center justify-center gap-1.5 transition" x-show="statusState === 'failed'">
                                        ↩️ Save &amp; Return to Editorial (EDAS Error)
                                    </button>

                                    <button type="submit" name="action" value="reviewer_approve" class="btn bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 text-xs font-extrabold w-full sm:w-auto shadow-sm flex items-center justify-center gap-1.5 transition" x-show="statusState === 'passed'">
                                        🏁 Mark Uploaded to EDAS (Complete Paper)
                                    </button>
                                </div>
                            @endif
                        </form>
                    @else
                        <div class="space-y-3 text-xs min-w-0">
                            @if(($submission->pdf_express_status ?? '') === 'failed' && $submission->edas_error_note)
                                <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-rose-900 break-words">
                                    <p class="font-extrabold text-xs">EDAS Error Notes:</p>
                                    <p class="mt-1 whitespace-pre-line leading-relaxed text-xs">{{ $submission->edas_error_note }}</p>
                                </div>
                            @endif
                        </div>
                    @endcan
                    @can('editorialReview', $submission)
                        @if($submission->status === \App\Enums\SubmissionStatus::ReadyForEdas)
                            <div class="pt-4 border-t border-navy/10 space-y-3">
                                <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="action" value="edas_fix">
                                    <textarea class="form-input min-h-16 py-2 text-xs" name="note" placeholder="EDAS error details"></textarea>
                                    <button class="btn btn-secondary w-full sm:w-auto text-xs font-bold py-2 px-4">Return due to EDAS Error</button>
                                </form>
                                @if(!$submission->edas_submitted_at)
                                    <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3 pt-3 border-t border-navy/10">
                                        @csrf
                                        <input type="hidden" name="action" value="record_edas">
                                        <input class="form-input text-xs" name="edas_reference" placeholder="EDAS ID / reference" required>
                                        <textarea class="form-input min-h-16 py-2 text-xs" name="note" placeholder="EDAS upload notes" required></textarea>
                                        <button class="btn btn-primary w-full sm:w-auto text-xs font-extrabold py-2 px-4">Record EDAS Upload</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @endcan
                </div>
            </details>

            <!-- 3. File Versioning Section (Accordion) -->
            <details class="card overflow-hidden max-w-full min-w-0">
                <summary class="flex cursor-pointer items-center justify-between p-4 sm:p-5 font-black text-navy bg-slate-50 hover:bg-slate-100 transition select-none border-b border-navy/8">
                    <div class="min-w-0">
                        <h2 class="text-sm sm:text-base font-black text-navy">File Versioning &amp; Attachments</h2>
                        <p class="text-[11px] text-muted font-normal truncate">Manuscript history (.docx/.zip) and Revision Guidance PDF.</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="badge badge-primary text-[10px]">{{ $submission->files->count() }} {{ Str::plural('file', $submission->files->count()) }}</span>
                        <span class="text-xs text-muted">▼</span>
                    </div>
                </summary>
                <div class="overflow-x-auto min-w-0 max-w-full">
                    <table class="data-table min-w-[560px]">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>File</th>
                                <th>Category</th>
                                <th>Source</th>
                                <th>Uploaded By</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($submission->files as $file)
                                <tr>
                                    <td class="whitespace-nowrap font-bold text-xs">
                                        v{{ $file->version_number }}
                                        @if($file->is_final)
                                            <span class="badge badge-success text-[10px] ml-1">Final</span>
                                        @endif
                                    </td>
                                    <td class="min-w-[180px]">
                                        <p class="font-bold text-navy text-xs sm:text-sm break-all leading-snug">{{ $file->label }}</p>
                                        <p class="text-xs text-muted break-all mt-0.5">{{ $file->original_name }} &middot; {{ number_format($file->size / 1024, 0) }} KB</p>
                                    </td>
                                    <td>
                                        <span class="badge {{ $file->file_category === 'revision_guidance_pdf' ? 'badge-warning' : 'badge-neutral' }} text-[10px]">
                                            {{ $file->file_category === 'revision_guidance_pdf' ? 'Revision Guidance PDF' : 'Editable Manuscript' }}
                                        </span>
                                    </td>
                                    <td class="text-xs capitalize">{{ $file->source }}</td>
                                    <td class="text-xs truncate max-w-[120px]">{{ $file->uploader?->name ?? 'Author' }}</td>
                                    <td class="text-right space-x-2 whitespace-nowrap">
                                        <a class="font-bold text-orange hover:underline text-xs" href="{{ route('submissions.files.preview', [$submission, $file]) }}">Preview</a>
                                        <a class="font-bold text-orange hover:underline text-xs" href="{{ route('submissions.files.download', [$submission, $file]) }}">Download</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($submission->uploadAttempts->where('status','failed')->isNotEmpty())
                    <div class="border-t border-danger/10 p-4 sm:p-6 bg-rose-50/40">
                        <h3 class="font-bold text-danger text-xs sm:text-sm mb-3">Upload Failed</h3>
                        <div class="space-y-3">
                            @foreach($submission->uploadAttempts->where('status','failed') as $attempt)
                                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-xl bg-white p-3.5 border border-rose-200 text-xs min-w-0">
                                    <div class="min-w-0">
                                        <p class="font-bold text-navy break-all">{{ $attempt->original_name }}</p>
                                        <p class="text-xs text-danger break-words mt-0.5">{{ Str::limit($attempt->error, 150) }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('submissions.uploads.retry', [$submission, $attempt]) }}" class="shrink-0 w-full sm:w-auto">
                                        @csrf
                                        <button class="btn btn-secondary text-xs px-3 py-1.5 w-full sm:w-auto">Retry</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @can('editorialReview', $submission)
                    <div class="border-t border-navy/10 p-4 sm:p-6 bg-slate-50/50">
                        <h3 class="font-extrabold text-navy text-xs sm:text-sm mb-3">Upload New File Version</h3>
                        <form method="POST" action="{{ route('submissions.files.store', $submission) }}" enctype="multipart/form-data" class="grid gap-4 grid-cols-1 sm:grid-cols-2">
                            @csrf
                            <div>
                                <label class="form-label text-xs">File Label *</label>
                                <input class="form-input text-xs" name="label" placeholder="e.g. Editorial Revision 1 / Final Camera Ready" required>
                            </div>
                            <div>
                                <label class="form-label text-xs">Select File *</label>
                                <input class="form-input text-xs py-2" type="file" name="paper_file" required>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label text-xs">File Notes (Optional)</label>
                                <textarea class="form-input text-xs min-h-20 py-2" name="notes" placeholder="Optional notes for this file version..."></textarea>
                            </div>
                            <div class="sm:col-span-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                                <label class="check-row h-auto min-h-11 py-2.5 px-4 flex items-center cursor-pointer rounded-xl border border-navy/15 bg-white hover:bg-slate-100 transition w-full sm:w-auto">
                                    <input type="checkbox" name="is_final" value="1" class="rounded text-orange focus:ring-orange shrink-0">
                                    <span class="text-xs font-bold text-navy ml-2">🏁 Mark as final file version</span>
                                </label>
                                <button type="submit" class="btn btn-primary px-5 py-2.5 text-xs font-extrabold w-full sm:w-auto">
                                    ⬆️ Upload New Version
                                </button>
                            </div>
                        </form>
                    </div>
                @endcan
            </details>

            <!-- 4. Email & Communication History Section (Accordion) -->
            @if($emailLogs->isNotEmpty() || app(\App\Services\VisibleEmailLogs::class)->canAccess(auth()->user()))
                <details class="card overflow-hidden max-w-full min-w-0" id="email-history-accordion">
                    <summary class="flex cursor-pointer items-center justify-between p-4 sm:p-5 font-black text-navy bg-slate-50 hover:bg-slate-100 transition select-none border-b border-navy/8">
                        <div class="min-w-0">
                            <h2 class="text-sm sm:text-base font-black text-navy">Email &amp; Communication History</h2>
                            <p class="text-[11px] text-muted font-normal truncate">Logs of automated &amp; manual email notifications dispatched for this manuscript.</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="badge badge-primary text-[10px]">{{ $emailLogs->count() }} {{ Str::plural('email', $emailLogs->count()) }}</span>
                            <span class="text-xs text-muted">▼</span>
                        </div>
                    </summary>
                    <div class="p-4 sm:p-6 border-t border-navy/8 space-y-4 bg-white">
                        <div class="flex items-center justify-between gap-3 pb-2 border-b border-navy/8">
                            <p class="text-xs text-slate-500 font-semibold">Dispatched email logs &amp; delivery status</p>
                            <a class="text-xs font-bold text-orange hover:underline flex items-center gap-1" href="{{ route('emails.index') }}">
                                View Full Email Monitoring ↗
                            </a>
                        </div>
                        <div class="space-y-3">
                            @forelse($emailLogs as $email)
                                <div class="rounded-xl bg-slate-50/80 p-3.5 border border-navy/10 text-xs min-w-0 shadow-xs">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="badge {{ $email->status === 'sent' ? 'badge-success' : ($email->status === 'failed' ? 'badge-danger' : 'badge-warning') }} text-[10px] uppercase font-black shrink-0">
                                                {{ $email->status }}
                                            </span>
                                            <span class="font-bold text-navy text-xs truncate">To: {{ $email->recipient }}</span>
                                            @if($email->cc && count($email->cc) > 0)
                                                <span class="text-[10px] text-slate-400 font-medium truncate">CC: {{ implode(', ', $email->cc) }}</span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-slate-500 shrink-0 font-medium">{{ $email->created_at->format('d M Y H:i:s') }}</span>
                                    </div>
                                    <p class="font-bold text-navy leading-snug break-words text-xs sm:text-sm">{{ $email->subject }}</p>
                                    @if($email->error)
                                        <p class="mt-2 text-[11px] text-rose-700 bg-rose-50 p-2.5 rounded-lg border border-rose-200 leading-relaxed break-words font-medium">{{ Str::limit($email->error, 250) }}</p>
                                    @endif
                                    @if($email->status === 'failed' && $email->body)
                                        <form class="mt-3 flex justify-end" method="POST" action="{{ route('emails.resend', $email) }}">
                                            @csrf
                                            <button class="btn btn-secondary px-3 py-1.5 text-xs font-black">🔁 Re-send Email</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-muted italic text-center py-4">No email history recorded for this manuscript.</p>
                            @endforelse
                        </div>
                    </div>
                </details>
            @endif
        </div>

        <!-- Sidebar Actions & Status -->
        <aside class="space-y-6 min-w-0 w-full max-w-full">
            @can('assign', $submission)
                <section class="card p-4 sm:p-6 space-y-6 max-w-full min-w-0">
                    <div>
                        <h2 class="font-black text-navy text-base">PIC Assignment</h2>
                        <p class="text-xs text-muted mt-0.5">Assign Editor &amp; Reviewer for this paper.</p>
                    </div>

                    <!-- Editor Assignment Form -->
                    <form method="POST" action="{{ route('submissions.assign', $submission) }}" class="space-y-4 rounded-2xl bg-warm/60 p-3.5 sm:p-4 border border-navy/10 min-w-0">
                        @csrf
                        <input type="hidden" name="role" value="editorial">
                        <div>
                            <label class="form-label text-xs">Editor PIC *</label>
                            <select class="form-input text-xs" name="user_id" required @if($submission->status->isTerminal()) disabled @endif>
                                <option value="">Select editor...</option>
                                @foreach($editors as $member)
                                    <option value="{{ $member->user_id }}" @selected($submission->editor_id === $member->user_id)>{{ $member->user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label text-xs">Author Document Format *</label>
                            <select class="form-input text-xs" name="manuscript_format" required @if($submission->status->isTerminal()) disabled @endif>
                                <option value="">Select format...</option>
                                <option value="docx" @selected($submission->manuscript_format === 'docx')>Microsoft Word (.docx)</option>
                                <option value="latex" @selected($submission->manuscript_format === 'latex')>LaTeX (.zip)</option>
                            </select>
                        </div>

                        @if($submission->editor_id)
                            <div>
                                <label class="form-label text-xs text-amber-700">Editor Reassignment Reason *</label>
                                <input class="form-input text-xs border-amber-300 bg-amber-50" name="reassignment_reason" placeholder="e.g. Workload rebalancing" required @if($submission->status->isTerminal()) disabled @endif>
                            </div>
                        @endif

                        <div>
                            <label class="form-label text-xs">Assignment Note (Optional)</label>
                            <input class="form-input text-xs" name="note" placeholder="Optional note for editor..." @if($submission->status->isTerminal()) disabled @endif>
                        </div>

                        <button class="btn btn-primary w-full py-2.5 text-xs font-extrabold" @if($submission->status->isTerminal()) disabled @endif>Save / Assign Editor</button>
                    </form>

                    <!-- Reviewer Assignment Form -->
                    <form method="POST" action="{{ route('submissions.assign', $submission) }}" class="space-y-4 rounded-2xl bg-warm/60 p-3.5 sm:p-4 border border-navy/10 min-w-0">
                        @csrf
                        <input type="hidden" name="role" value="reviewer">
                        <div>
                            <label class="form-label text-xs">Reviewer PIC *</label>
                            <select class="form-input text-xs" name="user_id" required @if($submission->status->isTerminal()) disabled @endif>
                                <option value="">Select reviewer...</option>
                                @foreach($reviewers as $member)
                                    <option value="{{ $member->user_id }}" @selected($submission->reviewer_id === $member->user_id)>{{ $member->user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($submission->reviewer_id)
                            <div>
                                <label class="form-label text-xs text-amber-700">Reviewer Reassignment Reason *</label>
                                <input class="form-input text-xs border-amber-300 bg-amber-50" name="reassignment_reason" placeholder="e.g. Reviewer availability change" required @if($submission->status->isTerminal()) disabled @endif>
                            </div>
                        @endif

                        <button class="btn btn-secondary w-full py-2.5 text-xs font-extrabold" @if($submission->status->isTerminal()) disabled @endif>Save / Assign Reviewer</button>
                    </form>
                    @can('assign', $submission)
                        <div class="mt-5 border-t border-navy/10 pt-5 space-y-3">
                            <h3 class="font-extrabold text-xs text-navy">Administrative Actions</h3>
                            <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-2">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <input class="form-input text-xs" name="note" placeholder="Rejection reason" required @if($submission->status->isTerminal()) disabled @endif>
                                <button class="btn btn-secondary w-full text-xs font-bold" @if($submission->status->isTerminal()) disabled @endif>Reject Paper</button>
                            </form>
                            <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-2">
                                @csrf
                                <input type="hidden" name="action" value="withdraw">
                                <input class="form-input text-xs" name="note" placeholder="Withdrawal reason" required @if($submission->status->isTerminal()) disabled @endif>
                                <button class="btn btn-secondary w-full text-xs font-bold" @if($submission->status->isTerminal()) disabled @endif>Withdraw Paper</button>
                            </form>
                        </div>
                    @endcan

                    @can('revertCompleted', $submission)
                        @if($submission->status === \App\Enums\SubmissionStatus::Done)
                            <div class="rounded-xl border border-amber-300 bg-amber-50/80 p-4 space-y-3 shadow-sm mt-5">
                                <h3 class="font-extrabold text-xs text-amber-900 flex items-center gap-1.5">
                                    Revert Completed Paper (Admin Only)
                                </h3>
                                <p class="text-[11px] text-amber-800 leading-snug">
                                    If completed by mistake or requiring further inspection, Conference Admin can revert this paper back to an active state.
                                </p>
                                <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3">
                                    @csrf
                                    <div>
                                        <label class="form-label text-xs">Revert Target Stage *</label>
                                        <select class="form-input text-xs" name="action" required>
                                            <option value="">Select target stage...</option>
                                            <option value="revert_done_to_editorial">🔙 Return to Editorial Compliance Check</option>
                                            <option value="revert_done_to_reviewer">🔙 Return to Peer &amp; Technical Review</option>
                                            <option value="revert_done_to_edas">🔙 Return to Ready for EDAS Upload</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Revert Reason / Note *</label>
                                        <textarea class="form-input min-h-16 py-2 text-xs" name="note" placeholder="State reason for reverting completed paper..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn text-xs font-extrabold w-full py-2 bg-amber-600 hover:bg-amber-700 text-white shadow-sm transition">
                                        🔄 Revert Paper Status
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endcan
                </section>
            @endcan

            <!-- Timeline Status Card -->
            <section class="card p-4 sm:p-6 max-w-full min-w-0">
                <h2 class="font-black text-navy text-base mb-4">Status Timeline</h2>
                <ol class="space-y-4 border-l-2 border-orange/40 pl-4">
                    @foreach($submission->statusHistory as $history)
                        <li class="relative">
                            <span class="absolute -left-[23px] top-1 size-3 rounded-full bg-orange ring-4 ring-warm"></span>
                            <span class="text-xs sm:text-sm font-bold text-navy block leading-tight">{{ $history->to_status->label() }}</span>
                            <p class="mt-0.5 text-[11px] text-muted">{{ $history->actor?->name ?? 'System' }} &middot; {{ $history->created_at->format('d M H:i') }}</p>
                            @if($history->note)
                                <p class="mt-1 text-xs text-slate-700 break-words leading-relaxed">{{ Str::limit($history->note, 100) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>

    <script>
    function generateRevisionFeedback(stageValue, isEditorialActive) {
        if (!isEditorialActive) return;
        const checklistForm = document.getElementById('checklist-form-' + stageValue);
        if (!checklistForm) return;

        let totalItems = 0;
        let failedCount = 0;

        checklistForm.querySelectorAll('table tbody tr').forEach((row) => {
            let checkInput = row.querySelector('.radio-check-input');
            if (!checkInput) return;
            totalItems++;
            if (!checkInput.checked) failedCount++;
        });

        if (totalItems === 0) {
            alert('No editorial checklist items found!');
            return;
        }

        if (failedCount === 0) {
            alert('All checklist items have passed! No items require revision.');
            return;
        }

        let templateHtml = 'Dear Authors,\n\n' +
            'Thank you for your submission. Your manuscript requires revision for ' + failedCount + ' item(s) before proceeding to peer review.\n\n' +
            '📌 REVISION INSTRUCTIONS:\n' +
            '1. Please open your private Author Portal and inspect the "Editorial Compliance Checklist Monitoring (Live)" card for specific items marked with "✕ Revision Needed".\n' +
            '2. ALWAYS USE THE LATEST MANUSCRIPT FILE available on your Author Portal as the base for your revisions, as the editorial team may have already performed initial formatting corrections on it.\n' +
            '3. ONLY REVISE THE SPECIFIC SECTIONS REQUESTED FOR CORRECTION. Please leave all other already compliant sections untouched.\n\n' +
            'Thank you for your cooperation.\n\n' +
            'Best regards,\n' +
            'Editorial Team';

        let feedbackEl = document.getElementById('author-feedback-textarea');
        if (feedbackEl) {
            feedbackEl.value = templateHtml;
            feedbackEl.scrollIntoView({ behavior: 'smooth' });
            feedbackEl.focus();
        }
    }
    </script>
</x-layouts.app>
