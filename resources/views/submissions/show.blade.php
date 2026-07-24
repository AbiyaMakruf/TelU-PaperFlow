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
            @endphp
            <a href="{{ route('author.portal', ['token' => $portalToken]) }}" target="_blank" rel="noopener" class="btn btn-secondary text-xs inline-flex items-center gap-1.5 shadow-sm hover:border-orange hover:text-orange" title="Inspect author portal view exactly as seen by the author">
                <span>👁️</span> Open Author Portal ↗
            </a>
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
                    <span class="text-xs text-muted">{{ $submission->submitted_at?->timezone($submission->conference->timezone)->format('d M Y H:i') }}</span>
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
            @foreach([\App\Enums\ReviewStage::Editorial, \App\Enums\ReviewStage::Reviewer] as $stage)
                @php
                    $allowed = $stage === \App\Enums\ReviewStage::Editorial ? auth()->user()->can('editorialReview', $submission) : auth()->user()->can('reviewerReview', $submission);
                    $template = $submission->conference->checklistTemplates->where('stage', $stage)->where('is_active', true)->first();
                    $cycle = $submission->reviewCycles->where('stage', $stage)->where('status', 'open')->first() ?? $submission->reviewCycles->where('stage', $stage)->first();
                @endphp
                @if($allowed && $template)
                    <details class="card overflow-hidden max-w-full min-w-0" @if(($stage === \App\Enums\ReviewStage::Editorial && $submission->status === \App\Enums\SubmissionStatus::EditorialReview) || ($stage === \App\Enums\ReviewStage::Reviewer && $submission->status === \App\Enums\SubmissionStatus::ReviewerReview)) open @endif>
                        <summary class="cursor-pointer list-none p-4 sm:p-6 text-base sm:text-lg font-black text-navy flex items-center justify-between select-none">
                            <span>Checklist {{ $stage->label() }}</span>
                            <span class="text-orange font-bold text-xl">+</span>
                        </summary>
                        <form method="POST" action="{{ route('submissions.checklist', [$submission, $stage->value]) }}" class="space-y-4 border-t border-navy/10 p-4 sm:p-6" id="checklist-form-{{ $stage->value }}">
                            @csrf
                            @method('PUT')

                            <!-- Quick Batch Action Buttons (Check All / Uncheck All) -->
                            <div class="flex flex-wrap items-center justify-between gap-2.5 bg-slate-50 p-3 rounded-xl border border-navy/10">
                                <span class="text-xs font-bold text-navy">Quick Actions:</span>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="
                                        document.querySelectorAll('#checklist-form-{{ $stage->value }} input[type=checkbox]').forEach(el => {
                                            el.checked = true;
                                            el.dispatchEvent(new Event('change', { bubbles: true }));
                                        });
                                    " class="btn text-xs py-1.5 px-3 bg-emerald-50 text-emerald-800 border border-emerald-300 hover:bg-emerald-100 font-extrabold shadow-sm transition">
                                        ✓ Check All
                                    </button>
                                    <button type="button" @click="
                                        document.querySelectorAll('#checklist-form-{{ $stage->value }} input[type=checkbox]').forEach(el => {
                                            el.checked = false;
                                            el.dispatchEvent(new Event('change', { bubbles: true }));
                                        });
                                    " class="btn text-xs py-1.5 px-3 bg-rose-50 text-rose-800 border border-rose-300 hover:bg-rose-100 font-extrabold shadow-sm transition">
                                        ✕ Uncheck All
                                    </button>
                                </div>
                            </div>
                            @foreach($template->items as $item)
                                @php($result = $cycle?->results->firstWhere('checklist_item_id', $item->id))
                                <div class="rounded-xl border border-navy/10 p-3.5 sm:p-4 min-w-0" x-data="{ openGuidance: false, checked: {{ json_encode((bool)($result?->is_checked)) }} }">
                                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2 sm:gap-3 min-w-0">
                                        <label class="flex items-start gap-2.5 flex-1 cursor-pointer min-w-0">
                                            <input class="mt-0.5 shrink-0 rounded text-orange focus:ring-orange" type="checkbox" name="items[{{ $item->id }}][checked]" value="1" x-model="checked" data-title="{{ e($item->title) }}" data-guidance="{{ e($item->description) }}">
                                            <div class="min-w-0">
                                                <strong class="text-navy text-xs sm:text-sm font-extrabold leading-snug break-words block">
                                                    {{ $item->title }} @if($item->is_required)<span class="text-orange">*</span>@endif
                                                </strong>
                                            </div>
                                        </label>
                                        @if($item->description)
                                            <button type="button" @click="openGuidance = !openGuidance" class="text-xs font-bold text-orange hover:underline shrink-0 self-start">
                                                <span x-text="openGuidance ? 'Close Guidance −' : 'Guidance Accordion +'"></span>
                                            </button>
                                        @endif
                                    </div>
                                    @if($item->description)
                                        <div x-show="openGuidance" x-cloak class="mt-3 rounded-lg bg-warm/80 p-3 text-xs leading-5 text-slate-700 border border-navy/8 break-words">
                                            <strong class="block text-navy font-bold mb-1">💡 Guidance / Check Details:</strong>
                                            <p class="leading-relaxed">{{ $item->description }}</p>
                                        </div>
                                    @endif
                                    <textarea class="form-input mt-3 min-h-16 py-2 text-xs" name="items[{{ $item->id }}][note]" placeholder="Item notes (optional)">{{ $result?->note }}</textarea>
                                </div>
                            @endforeach
                            <div class="flex items-center justify-end pt-3 border-t border-navy/10">
                                <button class="btn btn-primary px-5 py-2 text-xs font-extrabold w-full sm:w-auto">Save Checklist</button>
                            </div>
                        </form>
                    </details>
                @endif
            @endforeach

            @can('editorialReview', $submission)
                <!-- 1. Confidential Internal Notes (Accordion) -->
                <details class="card overflow-hidden border-l-4 border-l-navy bg-slate-50/50 max-w-full min-w-0">
                    <summary class="flex cursor-pointer items-center justify-between p-4 sm:p-5 font-black text-navy bg-slate-100/70 hover:bg-slate-200/60 transition select-none">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-base sm:text-lg">🔒</span>
                            <div class="min-w-0">
                                <h2 class="text-sm sm:text-base font-black text-navy">Internal Notes (Team Only)</h2>
                                <p class="text-[11px] text-muted font-normal truncate">Confidential notes for editorial team &amp; reviewers (hidden from author).</p>
                            </div>
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
                                        <span class="font-bold text-navy truncate">👤 {{ $feedback->author?->name ?? 'Staff Member' }}</span>
                                        <span class="shrink-0 text-[11px]">{{ $feedback->created_at->format('d M Y H:i') }}</span>
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
                                    💾 Save Internal Note
                                </button>
                            </div>
                        </form>
                    </div>
                </details>

                <!-- 2. Author Feedback & Communication (Accordion) -->
                <details class="card overflow-hidden border-l-4 border-l-orange bg-amber-50/20 max-w-full min-w-0" id="author-feedback-accordion" open>
                    <summary class="flex cursor-pointer items-center justify-between p-4 sm:p-5 font-black text-navy bg-amber-100/50 hover:bg-amber-100/80 transition select-none">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-base sm:text-lg">📩</span>
                            <div class="min-w-0">
                                <h2 class="text-sm sm:text-base font-black text-navy">Author Feedback &amp; Communication</h2>
                                <p class="text-[11px] text-muted font-normal truncate">This message will be visible to the author on the portal and can be sent via Email / WhatsApp.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="badge badge-warning text-[10px]">Visible to Author</span>
                            <span class="text-xs text-muted">▼</span>
                        </div>
                    </summary>
                    <div class="p-4 sm:p-6 border-t border-navy/8 space-y-4 bg-white">
                        <!-- Author Feedback History -->
                        <div class="space-y-3">
                            @forelse($submission->feedback->where('visibility', 'author') as $feedback)
                                <div class="rounded-xl bg-amber-50/40 p-3.5 border border-orange/20 shadow-sm text-xs min-w-0">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-muted">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="font-bold text-navy truncate">✉️ {{ $feedback->author?->name ?? 'Editorial' }}</span>
                                            @if($feedback->emailed_at)
                                                <span class="badge badge-success text-[9px] shrink-0">Sent via Email</span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] shrink-0">{{ $feedback->created_at->format('d M Y H:i') }}</span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line text-slate-800 leading-relaxed break-words">{{ $feedback->body }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-muted italic">No feedback sent to author yet.</p>
                            @endforelse
                        </div>

                        <!-- Author Feedback Form -->
                        <form method="POST" action="{{ route('submissions.feedback', $submission) }}" class="pt-2 border-t border-navy/8 space-y-4" id="author-feedback-form">
                            @csrf
                            <input type="hidden" name="visibility" value="author">

                            <div>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-1.5">
                                    <label class="form-label text-xs mb-0">Revision Feedback / Message for Author *</label>
                                    <button type="button" @click="
                                        let unchecked = [];
                                        document.querySelectorAll('input[type=checkbox][data-title]').forEach(el => {
                                            if (!el.checked) {
                                                let title = el.getAttribute('data-title');
                                                let guidance = el.getAttribute('data-guidance');
                                                unchecked.push('- ' + title + (guidance ? ' (' + guidance + ')' : ''));
                                            }
                                        });
                                        if (unchecked.length === 0) {
                                            alert('All checklist items are checked (Passed)!');
                                            return;
                                        }
                                        let text = 'Dear Author,\n\nPlease address the following items based on editorial inspection:\n\n' + unchecked.join('\n\n') + '\n\nBest regards,\nEditorial Team';
                                        let feedbackEl = document.getElementById('author-feedback-textarea');
                                        let accordionEl = document.getElementById('author-feedback-accordion');
                                        if (accordionEl) accordionEl.open = true;
                                        if (feedbackEl) {
                                            feedbackEl.value = text;
                                            feedbackEl.scrollIntoView({ behavior: 'smooth' });
                                            feedbackEl.focus();
                                        }
                                    " class="btn text-xs py-1.5 px-3 bg-amber-50 text-amber-900 border border-amber-300 hover:bg-amber-100 font-extrabold shadow-sm transition shrink-0">
                                        ⚡ Use Revision Template (Unchecked Items)
                                    </button>
                                </div>
                                <textarea class="form-input min-h-24 py-2.5 text-xs" name="body" id="author-feedback-textarea" placeholder="Write revision feedback or message for the author..." required></textarea>
                            </div>

                            <!-- Interactive CC Tag Input -->
                            <div x-data="{
                                ccInput: '',
                                tags: @js(old('cc') ? array_values(array_filter(preg_split('/[,;\s]+/', old('cc')))) : $defaultCc),
                                addTag() {
                                    let val = this.ccInput.trim().replace(/,$/, '');
                                    if (val && !this.tags.includes(val)) {
                                        this.tags.push(val);
                                    }
                                    this.ccInput = '';
                                },
                                removeTag(index) {
                                    this.tags.splice(index, 1);
                                }
                            }" class="min-w-0">
                                <label class="form-label text-xs mb-1 block">CC Email (Type email address and press comma / Enter)</label>
                                <input type="hidden" name="cc" :value="tags.join(',')">
                                <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-navy/20 bg-white p-2 min-h-11 focus-within:ring-2 focus-within:ring-orange max-w-full">
                                    <template x-for="(tag, index) in tags" :key="index">
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-navy text-white px-2 py-0.5 text-xs font-bold shadow-sm max-w-full truncate">
                                            <span x-text="tag" class="truncate"></span>
                                            <button type="button" @click="removeTag(index)" class="text-orange hover:text-white font-black text-sm leading-none ml-0.5 shrink-0">&times;</button>
                                        </span>
                                    </template>
                                    <input class="flex-1 bg-transparent text-xs border-0 focus:outline-none focus:ring-0 p-1 min-w-[120px]"
                                           x-model="ccInput"
                                           @keydown.comma.prevent="addTag()"
                                           @keydown.enter.prevent="addTag()"
                                           @blur="addTag()"
                                           placeholder="Type CC email...">
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2.5 pt-3 border-t border-navy/10">
                                <button type="submit" name="send_email" value="1" class="btn btn-primary px-4 py-2 text-xs font-extrabold w-full sm:w-auto">
                                    📧 Save &amp; Send Email Notification
                                </button>
                                @if($whatsappUrl)
                                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn px-4 py-2 text-xs font-extrabold bg-[#25D366] text-white hover:bg-[#1faa52] flex items-center justify-center gap-1.5 w-full sm:w-auto text-center">
                                        📱 Send via WhatsApp ↗
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </details>
            @endcan

            <!-- 3. File Versioning Section (Accordion) -->
            <details class="card overflow-hidden max-w-full min-w-0">
                <summary class="flex cursor-pointer items-center justify-between p-4 sm:p-5 font-black text-navy bg-slate-50 hover:bg-slate-100 transition select-none border-b border-navy/8">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-base sm:text-lg">📁</span>
                        <div class="min-w-0">
                            <h2 class="text-sm sm:text-base font-black text-navy">File Versioning &amp; Attachments</h2>
                            <p class="text-[11px] text-muted font-normal truncate">Manuscript history (.docx/.zip) and Revision Guidance PDF.</p>
                        </div>
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
                            <select class="form-input text-xs" name="user_id" required>
                                <option value="">Select editor...</option>
                                @foreach($editors as $member)
                                    <option value="{{ $member->user_id }}" @selected($submission->editor_id === $member->user_id)>{{ $member->user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label text-xs">Author Document Format *</label>
                            <select class="form-input text-xs" name="manuscript_format" required>
                                <option value="">Select format...</option>
                                <option value="docx" @selected($submission->manuscript_format === 'docx')>Microsoft Word (.docx)</option>
                                <option value="latex" @selected($submission->manuscript_format === 'latex')>LaTeX (.zip)</option>
                            </select>
                        </div>

                        @if($submission->editor_id)
                            <div>
                                <label class="form-label text-xs text-amber-700">Editor Reassignment Reason *</label>
                                <input class="form-input text-xs border-amber-300 bg-amber-50" name="reassignment_reason" placeholder="e.g. Workload rebalancing" required>
                            </div>
                        @endif

                        <div>
                            <label class="form-label text-xs">Assignment Note (Optional)</label>
                            <input class="form-input text-xs" name="note" placeholder="Optional note for editor...">
                        </div>

                        <button class="btn btn-primary w-full py-2.5 text-xs font-extrabold">Save / Assign Editor</button>
                    </form>

                    <!-- Reviewer Assignment Form -->
                    <form method="POST" action="{{ route('submissions.assign', $submission) }}" class="space-y-4 rounded-2xl bg-warm/60 p-3.5 sm:p-4 border border-navy/10 min-w-0">
                        @csrf
                        <input type="hidden" name="role" value="reviewer">
                        <div>
                            <label class="form-label text-xs">Reviewer PIC *</label>
                            <select class="form-input text-xs" name="user_id" required>
                                <option value="">Select reviewer...</option>
                                @foreach($reviewers as $member)
                                    <option value="{{ $member->user_id }}" @selected($submission->reviewer_id === $member->user_id)>{{ $member->user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($submission->reviewer_id)
                            <div>
                                <label class="form-label text-xs text-amber-700">Reviewer Reassignment Reason *</label>
                                <input class="form-input text-xs border-amber-300 bg-amber-50" name="reassignment_reason" placeholder="e.g. Reviewer availability change" required>
                            </div>
                        @endif

                        <button class="btn btn-secondary w-full py-2.5 text-xs font-extrabold">Save / Assign Reviewer</button>
                    </form>
                </section>
            @endcan

            <section class="card p-4 sm:p-6 border-2 border-orange/30 bg-amber-50/20 space-y-4 max-w-full min-w-0">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-navy/10 pb-3 min-w-0">
                    <div class="min-w-0">
                        <h2 class="font-black text-navy text-sm sm:text-base">IEEE PDF eXpress &amp; EDAS</h2>
                        <p class="text-[11px] text-muted">Reviewer status control panel</p>
                    </div>
                    <div class="shrink-0">
                        @if(($submission->pdf_express_status ?? '') === 'passed')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800 border border-emerald-300 shadow-sm">
                                🟢 PDF eXpress: Passed
                            </span>
                        @elseif(($submission->pdf_express_status ?? '') === 'failed')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-xs font-black text-rose-800 border border-rose-300 shadow-sm">
                                🔴 PDF eXpress: Failed
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800 border border-amber-300 shadow-sm">
                                🟡 PDF eXpress: Pending
                            </span>
                        @endif
                    </div>
                </div>

                @can('reviewerReview', $submission)
                    <form method="POST" action="{{ route('submissions.edas-status', $submission) }}" class="space-y-3 min-w-0" x-data="{
                        setError(msg) {
                            let current = $refs.noteInput.value;
                            $refs.noteInput.value = current ? current + '\n' + msg : msg;
                        }
                    }">
                        @csrf
                        <div>
                            <label class="form-label text-xs">IEEE PDF eXpress Status *</label>
                            <select class="form-input text-xs" name="pdf_express_status">
                                <option value="pending" @selected(($submission->pdf_express_status ?? 'pending') === 'pending')>Pending</option>
                                <option value="passed" @selected(($submission->pdf_express_status ?? '') === 'passed')>✓ Passed</option>
                                <option value="failed" @selected(($submission->pdf_express_status ?? '') === 'failed')>✕ Failed / Error</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs">EDAS Link / Reference</label>
                            <input class="form-input text-xs" name="edas_reference" value="{{ old('edas_reference', $submission->edas_reference) }}" placeholder="https://edas.info/manuscript/...">
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="form-label text-xs">EDAS Error Notes (Reviewer Only)</label>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mb-2">
                                <button type="button" @click="setError('pagesize: The page size is US letter size (8.5 by 11 inches), but only A4 size (210 x 297 mm) is allowed.')" class="text-[10px] bg-white border border-rose-200 text-rose-700 px-2 py-1 rounded hover:bg-rose-50 font-medium text-left leading-snug break-words">+ Page Size US Letter</button>
                                <button type="button" @click="setError('The final manuscript must have at least 5 filled pages, not just 4.')" class="text-[10px] bg-white border border-rose-200 text-rose-700 px-2 py-1 rounded hover:bg-rose-50 font-medium text-left leading-snug break-words">+ Min 5 Pages</button>
                                <button type="button" @click="setError('authorname: Doubleblind conference, but author names are visible on the first page.')" class="text-[10px] bg-white border border-rose-200 text-rose-700 px-2 py-1 rounded hover:bg-rose-50 font-medium text-left leading-snug break-words">+ Doubleblind Author Visible</button>
                                <button type="button" @click="setError('Authors must first upload or fill out the IEEE copyright form.')" class="text-[10px] bg-white border border-rose-200 text-rose-700 px-2 py-1 rounded hover:bg-rose-50 font-medium text-left leading-snug break-words">+ IEEE Copyright Missing</button>
                            </div>
                            <textarea x-ref="noteInput" class="form-input text-xs min-h-20" name="edas_error_note" placeholder="Write EDAS error details or click preset buttons above...">{{ old('edas_error_note', $submission->edas_error_note) }}</textarea>
                        </div>
                        <button class="btn btn-secondary w-full text-xs font-bold">Save Reviewer Status</button>
                    </form>
                @else
                    <div class="space-y-2 text-xs min-w-0">
                        <p class="font-semibold text-navy break-all"><strong>EDAS Reference:</strong> {{ $submission->edas_reference ?: '-' }}</p>
                        @if($submission->edas_error_note)
                            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-900 break-words">
                                <p class="font-bold">EDAS Error Notes:</p>
                                <p class="mt-1 whitespace-pre-line leading-relaxed">{{ $submission->edas_error_note }}</p>
                            </div>
                        @endif
                    </div>
                @endcan
            </section>

            <!-- Workflow Stage Actions Card -->
            <section class="card p-4 sm:p-6 max-w-full min-w-0">
                <h2 class="font-black text-navy text-base">Stage Actions</h2>
                <div class="mt-4 space-y-4 min-w-0">
                    @can('editorialReview', $submission)
                        @if($submission->status === \App\Enums\SubmissionStatus::EditorialReview)
                            <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="action" value="request_author_revision">
                                <textarea class="form-input min-h-24 py-3 text-xs" name="note" placeholder="Revision feedback for author" required></textarea>
                                <button class="btn btn-secondary w-full text-xs">Request Author Revision</button>
                            </form>
                            <form method="POST" action="{{ route('submissions.advance', $submission) }}">
                                @csrf
                                <input type="hidden" name="action" value="send_reviewer">
                                <button class="btn btn-primary w-full text-xs">Send to Reviewer</button>
                            </form>
                        @endif
                        @if($submission->status === \App\Enums\SubmissionStatus::ReadyForEdas)
                            <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="action" value="edas_fix">
                                <textarea class="form-input min-h-20 py-3 text-xs" name="note" placeholder="EDAS error details"></textarea>
                                <button class="btn btn-secondary w-full text-xs">Return due to EDAS Error</button>
                            </form>
                            @if(!$submission->edas_submitted_at)
                                <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3 border-t border-navy/10 pt-4">
                                    @csrf
                                    <input type="hidden" name="action" value="record_edas">
                                    <input class="form-input text-xs" name="edas_reference" placeholder="EDAS ID / reference" required>
                                    <textarea class="form-input min-h-20 py-3 text-xs" name="note" placeholder="EDAS upload notes" required></textarea>
                                    <button class="btn btn-primary w-full text-xs">Record EDAS Upload</button>
                                </form>
                            @endif
                        @endif
                    @endcan
                    @can('reviewerReview', $submission)
                        @if($submission->status === \App\Enums\SubmissionStatus::ReviewerReview)
                            <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="action" value="reviewer_changes">
                                <textarea class="form-input min-h-20 py-3 text-xs" name="note" placeholder="Note for editorial"></textarea>
                                <button class="btn btn-secondary w-full text-xs">Return to Editorial</button>
                            </form>
                            <form method="POST" action="{{ route('submissions.advance', $submission) }}">
                                @csrf
                                <input type="hidden" name="action" value="reviewer_approve">
                                <button class="btn btn-primary w-full text-xs">Approve &amp; Mark Ready for EDAS</button>
                            </form>
                        @endif
                        @if($submission->status === \App\Enums\SubmissionStatus::ReadyForEdas)
                            @if($submission->edas_submitted_at)
                                <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3 border-t border-navy/10 pt-4">
                                    @csrf
                                    <input type="hidden" name="action" value="approve_edas">
                                    <textarea class="form-input min-h-20 py-3 text-xs" name="note" placeholder="EDAS approval notes"></textarea>
                                    <button class="btn btn-primary w-full text-xs">Approve EDAS &amp; Mark Completed</button>
                                </form>
                            @endif
                        @endif
                    @endcan
                    @can('revertCompleted', $submission)
                        @if($submission->status === \App\Enums\SubmissionStatus::Done)
                            <div class="rounded-xl border border-amber-300 bg-amber-50/80 p-4 space-y-3 shadow-sm">
                                <h3 class="text-xs font-black text-amber-900 flex items-center gap-1.5">
                                    <span>🔄</span> Revert Completed Paper (Admin Only)
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

                    @can('assign', $submission)

                        <div class="mt-5 border-t border-navy/10 pt-5">
                            <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-2">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <input class="form-input text-xs" name="note" placeholder="Rejection reason" required>
                                <button class="btn btn-secondary w-full text-xs">Reject Paper</button>
                            </form>
                            <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="mt-3 space-y-2">
                                @csrf
                                <input type="hidden" name="action" value="withdraw">
                                <input class="form-input text-xs" name="note" placeholder="Withdrawal reason" required>
                                <button class="btn btn-secondary w-full text-xs">Withdraw Paper</button>
                            </form>
                        </div>
                    @endcan
                </div>
            </section>

            <!-- Email History Card -->
            @if($emailLogs->isNotEmpty() || app(\App\Services\VisibleEmailLogs::class)->canAccess(auth()->user()))
                <section class="card p-4 sm:p-6 max-w-full min-w-0">
                    <div class="flex items-center justify-between gap-3 border-b border-navy/8 pb-3">
                        <h2 class="font-black text-navy text-base">Email History</h2>
                        <a class="text-xs font-bold text-orange hover:underline" href="{{ route('emails.index') }}">Monitoring</a>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse($emailLogs as $email)
                            <div class="rounded-xl bg-warm/80 p-3.5 border border-navy/10 text-xs min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="badge {{ $email->status === 'sent' ? 'badge-success' : ($email->status === 'failed' ? 'badge-danger' : 'badge-warning') }} text-[10px] uppercase font-black shrink-0">
                                            {{ $email->status }}
                                        </span>
                                        <span class="text-muted text-[11px] truncate">To: {{ $email->recipient }}</span>
                                    </div>
                                    <span class="text-[11px] text-muted shrink-0">{{ $email->created_at->format('d M H:i') }}</span>
                                </div>
                                <p class="font-bold text-navy leading-snug break-words">{{ $email->subject }}</p>
                                @if($email->error)
                                    <p class="mt-2 text-[11px] text-danger bg-danger/5 p-2 rounded-lg leading-relaxed break-words">{{ Str::limit($email->error, 180) }}</p>
                                @endif
                                @if($email->status === 'failed' && $email->body)
                                    <form class="mt-3 flex justify-end" method="POST" action="{{ route('emails.resend', $email) }}">
                                        @csrf
                                        <button class="btn btn-secondary px-3 py-1.5 text-xs font-bold">🔁 Re-send Email</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-muted italic">No email history recorded.</p>
                        @endforelse
                    </div>
                </section>
            @endif

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
</x-layouts.app>
