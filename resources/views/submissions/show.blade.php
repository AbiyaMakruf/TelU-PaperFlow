<x-layouts.app :title="$submission->paper_code" heading="Detail paper">
    <a class="back-link" href="{{ route('submissions.index') }}">&larr; Kembali ke paper</a>
    <div class="mt-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div><p class="eyebrow">{{ $submission->conference->name }} · kode internal {{ $submission->paper_code }}</p><h1 class="page-title">{{ $submission->paper_id ?: $submission->paper_code }}</h1><p class="page-subtitle">{{ $submission->title }}</p></div>
        <span class="badge badge-{{ $submission->status->color() }}">{{ $submission->status->label() }}</span>
    </div>

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
                        <form method="POST" action="{{ route('submissions.checklist', [$submission, $stage->value]) }}" class="space-y-4 border-t border-navy/10 p-6">@csrf @method('PUT')
                            @foreach($template->items as $item)
                                @php($result = $cycle?->results->firstWhere('checklist_item_id', $item->id))
                                <div class="rounded-xl border border-navy/10 p-4"><label class="flex items-start gap-3"><input class="mt-1" type="checkbox" name="items[{{ $item->id }}][checked]" value="1" @checked($result?->is_checked)><span><strong class="text-navy">{{ $item->title }} @if($item->is_required)<span class="text-orange">*</span>@endif</strong>@if($item->description)<span class="mt-1 block text-sm text-muted">{{ $item->description }}</span>@endif</span></label><textarea class="form-input mt-3 min-h-20 py-3" name="items[{{ $item->id }}][note]" placeholder="Catatan item (opsional)">{{ $result?->note }}</textarea></div>
                            @endforeach
                            <button class="btn btn-primary">Simpan checklist</button>
                        </form>
                    </details>
                @endif
            @endforeach

            @can('editorialReview', $submission)
                <section class="card p-6">
                    <h2 class="text-lg font-black text-navy">Feedback &amp; komunikasi</h2>
                    <div class="mt-5 space-y-3">@forelse($submission->feedback as $feedback)<div class="rounded-xl bg-warm p-4"><div class="flex justify-between gap-3"><span class="badge {{ $feedback->visibility === 'author' ? 'badge-warning' : 'badge-primary' }}">{{ $feedback->visibility === 'author' ? 'Author' : 'Internal' }}</span><span class="text-xs text-muted">{{ $feedback->author?->name }} &middot; {{ $feedback->created_at->format('d M H:i') }}</span></div><p class="mt-3 whitespace-pre-line text-sm leading-6">{{ $feedback->body }}</p></div>@empty<p class="text-sm text-muted">Belum ada feedback.</p>@endforelse</div>
                    <form method="POST" action="{{ route('submissions.feedback', $submission) }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                        <textarea class="form-input min-h-28 py-3 sm:col-span-2" name="body" placeholder="Tulis feedback..." required></textarea>
                        <select class="form-input" name="visibility"><option value="internal">Catatan internal</option><option value="author">Terlihat author</option></select>
                        <input class="form-input" name="cc" placeholder="CC email, pisahkan koma">
                        <label class="check-row sm:col-span-2"><input type="checkbox" name="send_email" value="1"><span>Kirim email ke author (hanya untuk feedback author)</span></label>
                        <button class="btn btn-secondary sm:col-span-2">Simpan feedback</button>
                    </form>
                </section>
            @endcan

            <section class="card overflow-hidden">
                <div class="p-6"><h2 class="text-lg font-black text-navy">Versioning file</h2></div>
                <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Versi</th><th>File</th><th>Sumber</th><th>Oleh</th><th></th></tr></thead><tbody>@foreach($submission->files as $file)<tr><td>v{{ $file->version_number }} @if($file->is_final)<span class="badge badge-success">Final</span>@endif</td><td><p class="font-bold text-navy">{{ $file->label }}</p><p class="text-xs text-muted">{{ $file->original_name }} &middot; {{ number_format($file->size / 1024, 0) }} KB</p></td><td>{{ ucfirst($file->source) }}</td><td>{{ $file->uploader?->name ?? 'Author' }}</td><td class="space-x-3"><a class="font-bold text-orange" href="{{ route('submissions.files.preview', [$submission, $file]) }}">Preview</a><a class="font-bold text-orange" href="{{ route('submissions.files.download', [$submission, $file]) }}">Download</a></td></tr>@endforeach</tbody></table></div>
                @if($submission->uploadAttempts->where('status','failed')->isNotEmpty())<div class="border-t border-danger/10 p-6"><h3 class="font-bold text-danger">Upload gagal</h3>@foreach($submission->uploadAttempts->where('status','failed') as $attempt)<div class="mt-3 flex items-center justify-between rounded-xl bg-danger/5 p-4"><div><p class="font-bold">{{ $attempt->original_name }}</p><p class="text-xs text-danger">{{ Str::limit($attempt->error,150) }}</p></div><form method="POST" action="{{ route('submissions.uploads.retry',[$submission,$attempt]) }}">@csrf<button class="btn btn-secondary">Coba lagi</button></form></div>@endforeach</div>@endif
                @can('editorialReview', $submission)<form method="POST" action="{{ route('submissions.files.store', $submission) }}" enctype="multipart/form-data" class="grid gap-4 border-t border-navy/10 p-6 sm:grid-cols-2">@csrf<input class="form-input" name="label" placeholder="Label, mis. Revisi editorial 1" required><input class="form-input py-3" type="file" name="paper_file" required><textarea class="form-input min-h-20 py-3 sm:col-span-2" name="notes" placeholder="Catatan file"></textarea><label class="check-row"><input type="checkbox" name="is_final" value="1"><span>Tandai sebagai file final</span></label><button class="btn btn-primary">Upload versi</button></form>@endcan
            </section>
        </div>

        <aside class="space-y-6">
            @can('assign', $submission)
                <section class="card p-6"><h2 class="font-black text-navy">Assignment PIC</h2>
                    <form method="POST" action="{{ route('submissions.assign', $submission) }}" class="mt-4 space-y-3">@csrf<input type="hidden" name="role" value="editorial"><select class="form-input" name="user_id" required><option value="">Pilih editor...</option>@foreach($editors as $member)<option value="{{ $member->user_id }}" @selected($submission->editor_id === $member->user_id)>{{ $member->user->name }}</option>@endforeach</select><label><span class="form-label">Format dokumen author *</span><select class="form-input" name="manuscript_format" required><option value="">Pilih format...</option><option value="docx" @selected($submission->manuscript_format === 'docx')>Microsoft Word (.docx)</option><option value="latex" @selected($submission->manuscript_format === 'latex')>LaTeX (.zip)</option></select></label><input class="form-input" type="datetime-local" name="deadline_at" value="{{ $submission->deadline_at?->format('Y-m-d\TH:i') }}"><input class="form-input" name="note" placeholder="Catatan assignment"><button class="btn btn-secondary w-full">Assign editor</button></form>
                    <form method="POST" action="{{ route('submissions.assign', $submission) }}" class="mt-5 space-y-3 border-t border-navy/10 pt-5">@csrf<input type="hidden" name="role" value="reviewer"><select class="form-input" name="user_id" required><option value="">Pilih reviewer...</option>@foreach($reviewers as $member)<option value="{{ $member->user_id }}" @selected($submission->reviewer_id === $member->user_id)>{{ $member->user->name }}</option>@endforeach</select><button class="btn btn-secondary w-full">Assign reviewer</button></form>
                </section>
            @endcan

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
