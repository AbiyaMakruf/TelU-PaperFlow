<x-layouts.app :title="$submission->paper_code" heading="Detail paper">
    <a class="back-link" href="{{ route('submissions.index') }}">&larr; Kembali ke paper</a>
    <div class="mt-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div><p class="eyebrow">{{ $submission->conference->name }} · kode internal {{ $submission->paper_code }}</p><h1 class="page-title">{{ $submission->paper_id ?: $submission->paper_code }}</h1><p class="page-subtitle">{{ $submission->title }}</p></div>
        <span class="badge badge-{{ $submission->status->color() }}">{{ $submission->status->label() }}</span>
    </div>

    @if($submission->is_flagged_duplicate)
        <div class="mt-6 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-extrabold text-rose-900">⚠️ Peringatan Potensi Duplikat Submission</p>
            <p class="mt-1">{{ $submission->duplicate_notes }}</p>
        </div>
    @endif

    @if($errors->any())<div class="mt-6 rounded-xl border border-danger/20 bg-danger/8 p-4 text-sm text-danger"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mt-7 grid gap-6 xl:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            <section class="card p-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><h2 class="text-lg font-black text-navy">Data submission</h2><span class="text-xs text-muted">{{ $submission->submitted_at?->timezone($submission->conference->timezone)->format('d M Y H:i') }}</span></div>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Corresponding author</dt><dd class="mt-1 font-bold">{{ $submission->corresponding_author_name }}</dd><dd class="text-sm text-muted">{{ $submission->corresponding_author_email }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Telepon</dt><dd class="mt-1">{{ $submission->corresponding_author_phone ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Format editable</dt><dd class="mt-1">{{ $submission->manuscript_format === 'latex' ? 'LaTeX (ZIP)' : ($submission->manuscript_format === 'docx' ? 'Microsoft Word (DOCX)' : 'Belum dikonfirmasi') }}</dd></div>
                    @foreach($submission->formVersion?->schema ?? [] as $field)@continue($field['key'] === 'co_authors')<div><dt class="text-xs font-bold uppercase tracking-wider text-muted">{{ $field['label'] }}</dt><dd class="mt-1 whitespace-pre-line">{{ $submission->answers[$field['key']] ?? '-' }}</dd></div>@endforeach
                </dl>
                @if($submission->authors->count() > 1)<div class="mt-5 border-t border-navy/10 pt-5"><h3 class="font-bold text-navy">Co-author</h3><div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach($submission->authors->where('is_corresponding',false) as $author)<div class="rounded-xl bg-warm p-4"><p class="font-bold">{{ $author->name }}</p><p class="text-xs text-muted">{{ $author->email ?: '-' }} @if($author->affiliation)· {{ $author->affiliation }}@endif</p></div>@endforeach</div></div>@endif
                @can('assign', $submission)
                    @if($submission->status === \App\Enums\SubmissionStatus::Submitted)
                        <div class="mt-6 grid gap-4 border-t border-navy/10 pt-6 md:grid-cols-2">
                            <form method="POST" action="{{ route('submissions.accept', $submission) }}">@csrf<button class="btn btn-primary w-full">Data valid &amp; lanjut assignment</button></form>
                            <form method="POST" action="{{ route('submissions.correction', $submission) }}" class="space-y-3">@csrf<textarea class="form-input min-h-24 py-3" name="feedback" placeholder="Jelaskan data yang harus diperbaiki..." required></textarea><button class="btn btn-secondary w-full">Kembalikan ke author</button></form>
                        </div>
                    @endif
                @endcan
            </section>

            @foreach([\App\Enums\ReviewStage::Editorial, \App\Enums\ReviewStage::Reviewer] as $stage)
                @php
                    $allowed = $stage === \App\Enums\ReviewStage::Editorial ? auth()->user()->can('editorialReview', $submission) : auth()->user()->can('reviewerReview', $submission);
                    $template = $submission->conference->checklistTemplates->where('stage', $stage)->where('is_active', true)->first();
                    $cycle = $submission->reviewCycles->where('stage', $stage)->where('status', 'open')->first() ?? $submission->reviewCycles->where('stage', $stage)->first();
                @endphp
                @if($allowed && $template)
                    <details class="card overflow-hidden" @if(($stage === \App\Enums\ReviewStage::Editorial && $submission->status === \App\Enums\SubmissionStatus::EditorialReview) || ($stage === \App\Enums\ReviewStage::Reviewer && $submission->status === \App\Enums\SubmissionStatus::ReviewerReview)) open @endif>
                        <summary class="cursor-pointer list-none p-6 text-lg font-black text-navy">Checklist {{ $stage->label() }} <span class="float-right text-orange">+</span></summary>
                        <form method="POST" action="{{ route('submissions.checklist', [$submission, $stage->value]) }}" class="space-y-4 border-t border-navy/10 p-6" id="checklist-form-{{ $stage->value }}">
                            @csrf @method('PUT')
                            @foreach($template->items as $item)
                                @php($result = $cycle?->results->firstWhere('checklist_item_id', $item->id))
                                <div class="rounded-xl border border-navy/10 p-4" x-data="{ openGuidance: false, checked: {{ json_encode((bool)($result?->is_checked)) }} }">
                                    <div class="flex items-start justify-between gap-3">
                                        <label class="flex items-start gap-3 flex-1 cursor-pointer">
                                            <input class="mt-1" type="checkbox" name="items[{{ $item->id }}][checked]" value="1" x-model="checked" data-title="{{ e($item->title) }}" data-guidance="{{ e($item->description) }}">
                                            <div>
                                                <strong class="text-navy text-sm font-extrabold">{{ $item->title }} @if($item->is_required)<span class="text-orange">*</span>@endif</strong>
                                            </div>
                                        </label>
                                        @if($item->description)
                                            <button type="button" @click="openGuidance = !openGuidance" class="text-xs font-bold text-orange hover:underline shrink-0">
                                                <span x-text="openGuidance ? 'Tutup Guidance −' : 'Guidance Accordion +'"></span>
                                            </button>
                                        @endif
                                    </div>
                                    @if($item->description)
                                        <div x-show="openGuidance" x-cloak class="mt-3 rounded-lg bg-warm/80 p-3 text-xs leading-5 text-slate-700 border border-navy/8">
                                            <strong class="block text-navy font-bold mb-1">💡 Guidance / Detail Pemeriksaan:</strong>
                                            <p>{{ $item->description }}</p>
                                        </div>
                                    @endif
                                    <textarea class="form-input mt-3 min-h-16 py-2 text-xs" name="items[{{ $item->id }}][note]" placeholder="Catatan item (opsional)">{{ $result?->note }}</textarea>
                                </div>
                            @endforeach
                            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-navy/10">
                                <button class="btn btn-primary">Simpan Checklist</button>
                                <button type="button" @click="
                                    let unchecked = [];
                                    document.querySelectorAll('#checklist-form-{{ $stage->value }} input[type=checkbox]').forEach(el => {
                                        if (!el.checked) {
                                            let title = el.getAttribute('data-title');
                                            let guidance = el.getAttribute('data-guidance');
                                            unchecked.push('• ' + title + (guidance ? ': ' + guidance : ''));
                                        }
                                    });
                                    if (unchecked.length === 0) {
                                        alert('Seluruh item checklist sudah dicentang (OK)!');
                                        return;
                                    }
                                    let text = 'Halo Author,\n\nMohon perbaiki poin-poin berikut berdasarkan hasil pemeriksaan editorial:\n\n' + unchecked.join('\n\n') + '\n\nTerima kasih,\nTim Editorial';
                                    let feedbackEl = document.querySelector('textarea[name=body]');
                                    if (feedbackEl) {
                                        feedbackEl.value = text;
                                        feedbackEl.scrollIntoView({ behavior: 'smooth' });
                                        feedbackEl.focus();
                                    }
                                " class="btn btn-secondary text-xs">
                                    ⚡ Gunakan Template Revisi (Unchecked Items)
                                </button>
                            </div>
                        </form>
                    </details>
                @endif
            @endforeach

            @can('editorialReview', $submission)
                <section class="card p-6">
                    <h2 class="text-lg font-black text-navy">Feedback &amp; komunikasi</h2>
                    <div class="mt-5 space-y-3">@forelse($submission->feedback as $feedback)<div class="rounded-xl bg-warm p-4"><div class="flex justify-between gap-3"><span class="badge {{ $feedback->visibility === 'author' ? 'badge-warning' : 'badge-primary' }}">{{ $feedback->visibility === 'author' ? 'Author' : 'Internal' }}</span><span class="text-xs text-muted">{{ $feedback->author?->name }} &middot; {{ $feedback->created_at->format('d M H:i') }}</span></div><p class="mt-3 whitespace-pre-line text-sm leading-6">{{ $feedback->body }}</p></div>@empty<p class="text-sm text-muted">Belum ada feedback.</p>@endforelse</div>
                    <form method="POST" action="{{ route('submissions.feedback', $submission) }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                        <textarea class="form-input min-h-28 py-3 sm:col-span-2 text-sm" name="body" placeholder="Tulis feedback..." required></textarea>
                        <select class="form-input" name="visibility"><option value="internal">Catatan internal</option><option value="author">Terlihat author</option></select>
                        <!-- Interactive CC Tag Input -->
                        <div x-data="{
                            ccInput: '',
                            tags: {{ json_encode(array_values(array_filter(explode(',', old('cc', ''))))) }},
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
                        }" class="sm:col-span-2">
                            <label class="form-label mb-1 block">CC Email (Ketik email lalu tekan koma / Enter)</label>
                            <input type="hidden" name="cc" :value="tags.join(',')">
                            <div class="flex flex-wrap items-center gap-2 rounded-xl border border-navy/20 bg-white p-2 min-h-12 focus-within:ring-2 focus-within:ring-orange">
                                <template x-for="(tag, index) in tags" :key="index">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-navy text-white px-3 py-1 text-xs font-bold shadow-sm">
                                        <span x-text="tag"></span>
                                        <button type="button" @click="removeTag(index)" class="text-orange hover:text-white font-black text-sm leading-none ml-1">&times;</button>
                                    </span>
                                </template>
                                <input class="flex-1 bg-transparent text-xs border-0 focus:outline-none focus:ring-0 p-1 min-w-[200px]"
                                       x-model="ccInput"
                                       @keydown.comma.prevent="addTag()"
                                       @keydown.enter.prevent="addTag()"
                                       @blur="addTag()"
                                       placeholder="Ketik email CC...">
                            </div>
                        </div>

                        <label class="check-row sm:col-span-2"><input type="checkbox" name="send_email" value="1"><span>Kirim email ke author (hanya untuk feedback author)</span></label>
                        <button class="btn btn-secondary sm:col-span-2">Simpan feedback</button>
                    </form>
                </section>
            @endcan

            <section class="card overflow-hidden">
                <div class="p-6"><h2 class="text-lg font-black text-navy">Versioning file</h2></div>
                <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Versi</th><th>File</th><th>Kategori</th><th>Sumber</th><th>Oleh</th><th></th></tr></thead><tbody>@foreach($submission->files as $file)<tr><td>v{{ $file->version_number }} @if($file->is_final)<span class="badge badge-success">Final</span>@endif</td><td><p class="font-bold text-navy">{{ $file->label }}</p><p class="text-xs text-muted">{{ $file->original_name }} &middot; {{ number_format($file->size / 1024, 0) }} KB</p></td><td><span class="badge {{ $file->file_category === 'revision_guidance_pdf' ? 'badge-warning' : 'badge-neutral' }} text-[10px]">{{ $file->file_category === 'revision_guidance_pdf' ? 'PDF Petunjuk Revisi' : 'Editable Manuscript' }}</span></td><td>{{ ucfirst($file->source) }}</td><td>{{ $file->uploader?->name ?? 'Author' }}</td><td class="space-x-3"><a class="font-bold text-orange" href="{{ route('submissions.files.preview', [$submission, $file]) }}">Preview</a><a class="font-bold text-orange" href="{{ route('submissions.files.download', [$submission, $file]) }}">Download</a></td></tr>@endforeach</tbody></table></div>
                @if($submission->uploadAttempts->where('status','failed')->isNotEmpty())<div class="border-t border-danger/10 p-6"><h3 class="font-bold text-danger">Upload gagal</h3>@foreach($submission->uploadAttempts->where('status','failed') as $attempt)<div class="mt-3 flex items-center justify-between rounded-xl bg-danger/5 p-4"><div><p class="font-bold">{{ $attempt->original_name }}</p><p class="text-xs text-danger">{{ Str::limit($attempt->error,150) }}</p></div><form method="POST" action="{{ route('submissions.uploads.retry',[$submission,$attempt]) }}">@csrf<button class="btn btn-secondary">Coba lagi</button></form></div>@endforeach</div>@endif
                @can('editorialReview', $submission)<form method="POST" action="{{ route('submissions.files.store', $submission) }}" enctype="multipart/form-data" class="grid gap-4 border-t border-navy/10 p-6 sm:grid-cols-2">@csrf<input class="form-input" name="label" placeholder="Label, mis. Revisi editorial 1" required><input class="form-input py-3" type="file" name="paper_file" required><textarea class="form-input min-h-20 py-3 sm:col-span-2" name="notes" placeholder="Catatan file"></textarea><label class="check-row"><input type="checkbox" name="is_final" value="1"><span>Tandai sebagai file final</span></label><button class="btn btn-primary">Upload versi</button></form>@endcan
            </section>
        </div>

        <aside class="space-y-6">
            @can('assign', $submission)
                <section class="card p-6"><h2 class="font-black text-navy">Assignment PIC</h2>
                    <form method="POST" action="{{ route('submissions.assign', $submission) }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="role" value="editorial">
                        <select class="form-input" name="user_id" required>
                            <option value="">Pilih editor...</option>
                            @foreach($editors as $member)
                                <option value="{{ $member->user_id }}" @selected($submission->editor_id === $member->user_id)>{{ $member->user->name }}</option>
                            @endforeach
                        </select>
                        <label><span class="form-label">Format dokumen author *</span><select class="form-input" name="manuscript_format" required><option value="">Pilih format...</option><option value="docx" @selected($submission->manuscript_format === 'docx')>Microsoft Word (.docx)</option><option value="latex" @selected($submission->manuscript_format === 'latex')>LaTeX (.zip)</option></select></label>
                        <input class="form-input" type="datetime-local" name="deadline_at" value="{{ $submission->deadline_at?->format('Y-m-d\TH:i') }}">
                        @if($submission->editor_id)
                            <input class="form-input border-amber-300 bg-amber-50" name="reassignment_reason" placeholder="Alasan mengganti Editor (wajib)">
                        @endif
                        <input class="form-input" name="note" placeholder="Catatan assignment">
                        <button class="btn btn-secondary w-full">Assign editor</button>
                    </form>
                    <form method="POST" action="{{ route('submissions.assign', $submission) }}" class="mt-5 space-y-3 border-t border-navy/10 pt-5">
                        @csrf
                        <input type="hidden" name="role" value="reviewer">
                        <select class="form-input" name="user_id" required>
                            <option value="">Pilih reviewer...</option>
                            @foreach($reviewers as $member)
                                <option value="{{ $member->user_id }}" @selected($submission->reviewer_id === $member->user_id)>{{ $member->user->name }}</option>
                            @endforeach
                        </select>
                        @if($submission->reviewer_id)
                            <input class="form-input border-amber-300 bg-amber-50" name="reassignment_reason" placeholder="Alasan mengganti Reviewer (wajib)">
                        @endif
                        <button class="btn btn-secondary w-full">Assign reviewer</button>
                    </form>
                </section>
            @endcan

            <section class="card p-6 border-2 border-orange/30 bg-amber-50/20">
                <div class="flex items-center justify-between">
                    <h2 class="font-black text-navy text-base">IEEE PDF eXpress &amp; EDAS</h2>
                    <span class="badge {{ $submission->pdf_express_status === 'passed' ? 'badge-success' : ($submission->pdf_express_status === 'failed' ? 'badge-danger' : 'badge-warning') }}">
                        PDF eXpress: {{ ucfirst($submission->pdf_express_status ?? 'pending') }}
                    </span>
                </div>

                @can('reviewerReview', $submission)
                    <form method="POST" action="{{ route('submissions.edas-status', $submission) }}" class="mt-4 space-y-3" x-data="{
                        setError(msg) {
                            let current = $refs.noteInput.value;
                            $refs.noteInput.value = current ? current + '\n' + msg : msg;
                        }
                    }">
                        @csrf
                        <div>
                            <label class="form-label text-xs">Status IEEE PDF eXpress *</label>
                            <select class="form-input text-xs" name="pdf_express_status">
                                <option value="pending" @selected(($submission->pdf_express_status ?? 'pending') === 'pending')>Pending (Belum Diperiksa)</option>
                                <option value="passed" @selected(($submission->pdf_express_status ?? '') === 'passed')>✓ Passed / Done</option>
                                <option value="failed" @selected(($submission->pdf_express_status ?? '') === 'failed')>✕ Failed / Error</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs">Link / Referensi EDAS</label>
                            <input class="form-input text-xs" name="edas_reference" value="{{ old('edas_reference', $submission->edas_reference) }}" placeholder="https://edas.info/manuscript/...">
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="form-label text-xs">Catatan Error EDAS (Reviewer Only)</label>
                            </div>
                            <div class="flex flex-wrap gap-1 mb-2">
                                <button type="button" @click="setError('pagesize: The page size is US letter size (8.5 by 11 inches), but only A4 size (210 x 297 mm) is allowed.')" class="text-[10px] bg-white border border-rose-200 text-rose-700 px-2 py-0.5 rounded hover:bg-rose-50">+ Page Size US Letter</button>
                                <button type="button" @click="setError('The final manuscript must have at least 5 filled pages, not just 4.')" class="text-[10px] bg-white border border-rose-200 text-rose-700 px-2 py-0.5 rounded hover:bg-rose-50">+ Min 5 Pages</button>
                                <button type="button" @click="setError('authorname: Doubleblind conference, but author names are visible on the first page.')" class="text-[10px] bg-white border border-rose-200 text-rose-700 px-2 py-0.5 rounded hover:bg-rose-50">+ Doubleblind Author Visible</button>
                                <button type="button" @click="setError('Authors must first upload or fill out the IEEE copyright form.')" class="text-[10px] bg-white border border-rose-200 text-rose-700 px-2 py-0.5 rounded hover:bg-rose-50">+ IEEE Copyright Missing</button>
                            </div>
                            <textarea x-ref="noteInput" class="form-input text-xs min-h-20" name="edas_error_note" placeholder="Tulis rincian error EDAS atau klik tombol preset di atas...">{{ old('edas_error_note', $submission->edas_error_note) }}</textarea>
                        </div>
                        <button class="btn btn-secondary w-full text-xs">Simpan Status Reviewer</button>
                    </form>
                @else
                    <div class="mt-3 space-y-2 text-xs">
                        <p><strong>EDAS Ref:</strong> {{ $submission->edas_reference ?: '-' }}</p>
                        @if($submission->edas_error_note)
                            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-900">
                                <p class="font-bold">Catatan Error EDAS:</p>
                                <p class="mt-1 whitespace-pre-line">{{ $submission->edas_error_note }}</p>
                            </div>
                        @endif
                    </div>
                @endcan
            </section>

            <section class="card p-6"><h2 class="font-black text-navy">Aksi tahap</h2><div class="mt-4 space-y-4">
                @can('editorialReview', $submission)
                    @if($submission->status === \App\Enums\SubmissionStatus::EditorialReview)
                        <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3">@csrf<input type="hidden" name="action" value="request_author_revision"><textarea class="form-input min-h-24 py-3" name="note" placeholder="Feedback revisi untuk author" required></textarea><button class="btn btn-secondary w-full">Minta revisi author</button></form>
                        <form method="POST" action="{{ route('submissions.advance', $submission) }}">@csrf<input type="hidden" name="action" value="send_reviewer"><button class="btn btn-primary w-full">Kirim ke reviewer</button></form>
                    @endif
                    @if($submission->status === \App\Enums\SubmissionStatus::ReadyForEdas)
                        <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3">@csrf<input type="hidden" name="action" value="edas_fix"><textarea class="form-input min-h-20 py-3" name="note" placeholder="Error/perbaikan EDAS"></textarea><button class="btn btn-secondary w-full">Kembalikan karena error EDAS</button></form>
                        @if(!$submission->edas_submitted_at)<form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3 border-t border-navy/10 pt-4">@csrf<input type="hidden" name="action" value="record_edas"><input class="form-input" name="edas_reference" placeholder="EDAS ID / reference" required><textarea class="form-input min-h-20 py-3" name="note" placeholder="Catatan upload EDAS" required></textarea><button class="btn btn-primary w-full">Catat sudah upload EDAS</button></form>@endif
                    @endif
                @endcan
                @can('reviewerReview', $submission)
                    @if($submission->status === \App\Enums\SubmissionStatus::ReviewerReview)
                        <form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3">@csrf<input type="hidden" name="action" value="reviewer_changes"><textarea class="form-input min-h-20 py-3" name="note" placeholder="Catatan untuk editorial"></textarea><button class="btn btn-secondary w-full">Kembalikan ke editorial</button></form>
                        <form method="POST" action="{{ route('submissions.advance', $submission) }}">@csrf<input type="hidden" name="action" value="reviewer_approve"><button class="btn btn-primary w-full">Setujui &amp; ready for EDAS</button></form>
                    @endif
                    @if($submission->status === \App\Enums\SubmissionStatus::ReadyForEdas)
                        @if($submission->edas_submitted_at)<form method="POST" action="{{ route('submissions.advance', $submission) }}" class="space-y-3 border-t border-navy/10 pt-4">@csrf<input type="hidden" name="action" value="approve_edas"><textarea class="form-input min-h-20 py-3" name="note" placeholder="Catatan approval EDAS"></textarea><button class="btn btn-primary w-full">Approve EDAS &amp; selesai</button></form>@endif
                    @endif
                @endcan
                @can('assign',$submission)<div class="mt-5 border-t border-navy/10 pt-5"><form method="POST" action="{{ route('submissions.advance',$submission) }}" class="space-y-2">@csrf<input type="hidden" name="action" value="reject"><input class="form-input" name="note" placeholder="Alasan reject" required><button class="btn btn-secondary w-full">Reject paper</button></form><form method="POST" action="{{ route('submissions.advance',$submission) }}" class="mt-3 space-y-2">@csrf<input type="hidden" name="action" value="withdraw"><input class="form-input" name="note" placeholder="Alasan withdraw" required><button class="btn btn-secondary w-full">Withdraw paper</button></form></div>@endcan
            </div></section>

            <section class="card p-6"><h2 class="font-black text-navy">Riwayat email</h2><div class="mt-4 space-y-3">@forelse($submission->emailLogs as $email)<div class="rounded-xl bg-warm p-4"><div class="flex justify-between"><strong>{{ $email->subject }}</strong><span class="badge {{ $email->status==='sent'?'badge-success':($email->status==='failed'?'badge-danger':'badge-warning') }}">{{ $email->status }}</span></div><p class="mt-1 text-xs text-muted">Ke {{ $email->recipient }} &middot; {{ $email->created_at->format('d M H:i') }}</p>@if($email->error)<p class="mt-2 text-xs text-danger">{{ Str::limit($email->error,180) }}</p>@endif</div>@empty<p class="text-sm text-muted">Belum ada email.</p>@endforelse</div></section>

            <section class="card p-6"><h2 class="font-black text-navy">Timeline status</h2><ol class="mt-5 space-y-5 border-l-2 border-navy/10 pl-5">@foreach($submission->statusHistory as $history)<li><span class="-ml-[27px] mr-3 inline-block size-3 rounded-full bg-orange ring-4 ring-warm"></span><span class="text-sm font-bold text-navy">{{ $history->to_status->label() }}</span><p class="mt-1 text-xs text-muted">{{ $history->actor?->name ?? 'Sistem' }} &middot; {{ $history->created_at->format('d M H:i') }}</p>@if($history->note)<p class="mt-1 text-xs">{{ Str::limit($history->note, 100) }}</p>@endif</li>@endforeach</ol></section>
        </aside>
    </div>
</x-layouts.app>
