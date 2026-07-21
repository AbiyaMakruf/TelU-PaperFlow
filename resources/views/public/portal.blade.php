<x-layouts.public :title="$submission->paper_code">
    <div class="mx-auto max-w-5xl">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
            <div>
                <p class="eyebrow">Author portal &middot; {{ $submission->conference->name }}</p>
                <h1 class="page-title">{{ $submission->paper_code }}</h1>
                <p class="page-subtitle">{{ $submission->title }}</p>
            </div>
            <span class="badge badge-{{ $submission->status->color() }}">{{ $submission->status->label() }}</span>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1.4fr_.6fr]">
            <section class="space-y-6">
                <!-- Edit Detail Submission Card -->
                <div class="card p-6" x-data="{ openEdit: false }">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-black text-navy">Detail Submission</h2>
                            <p class="text-xs text-muted">Judul, data corresponding author, dan daftar co-authors.</p>
                        </div>
                        <button type="button" @click="openEdit = !openEdit" class="btn btn-secondary text-xs">
                            <span x-text="openEdit ? 'Batal Edit' : 'Edit Detail Submission'"></span>
                        </button>
                    </div>

                    <div x-show="!openEdit" class="mt-5 space-y-3 text-sm">
                        <div><span class="text-xs font-bold text-muted">Judul Paper:</span><p class="font-bold text-navy mt-0.5">{{ $submission->title }}</p></div>
                        <div class="grid grid-cols-2 gap-4 pt-2">
                            <div><span class="text-xs font-bold text-muted">Corresponding Author:</span><p class="font-semibold text-navy mt-0.5">{{ $submission->corresponding_author_name }}</p><p class="text-xs text-muted">{{ $submission->corresponding_author_email }}</p></div>
                            <div><span class="text-xs font-bold text-muted">Nomor Telepon/WA:</span><p class="font-semibold text-navy mt-0.5">{{ $submission->corresponding_author_phone ?: '-' }}</p></div>
                        </div>
                        @if($submission->authors->where('is_corresponding', false)->isNotEmpty())
                            <div class="pt-2">
                                <span class="text-xs font-bold text-muted">Co-Authors:</span>
                                <ul class="mt-1 list-disc list-inside text-xs text-slate-700 space-y-1">
                                    @foreach($submission->authors->where('is_corresponding', false) as $co)
                                        <li>{{ $co->name }} ({{ $co->email ?: 'Tanpa email' }}) - {{ $co->affiliation ?: '-' }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <form x-show="openEdit" x-cloak method="POST" action="{{ route('author.details.update', $token) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="form-label">Judul Paper *</label>
                            <input class="form-input" name="title" value="{{ old('title', $submission->title) }}" required>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="form-label">Nama Corresponding Author *</label>
                                <input class="form-input" name="author_name" value="{{ old('author_name', $submission->corresponding_author_name) }}" required>
                            </div>
                            <div>
                                <label class="form-label">Email Corresponding Author *</label>
                                <input class="form-input" type="email" name="author_email" value="{{ old('author_email', $submission->corresponding_author_email) }}" required>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Nomor Telepon/WA *</label>
                            <input class="form-input" name="author_phone" value="{{ old('author_phone', $submission->corresponding_author_phone) }}" required>
                        </div>

                        <!-- Co-authors list editor -->
                        <div class="pt-2" x-data="{
                            coAuthors: {{ Js::from($submission->authors->where('is_corresponding', false)->values()->map(fn($a) => ['name' => $a->name, 'email' => $a->email, 'affiliation' => $a->affiliation])) }}
                        }">
                            <div class="flex items-center justify-between mb-2">
                                <span class="form-label">Daftar Co-Authors</span>
                                <button type="button" @click="coAuthors.push({name: '', email: '', affiliation: ''})" class="text-xs font-bold text-orange hover:underline">+ Tambah Co-author</button>
                            </div>
                            <template x-for="(co, index) in coAuthors" :key="index">
                                <div class="grid gap-2 sm:grid-cols-3 items-center mb-2 p-3 bg-warm/50 rounded-xl">
                                    <input class="form-input text-xs" :name="`co_authors[${index}][name]`" x-model="co.name" placeholder="Nama lengkap *" required>
                                    <input class="form-input text-xs" type="email" :name="`co_authors[${index}][email]`" x-model="co.email" placeholder="Email (opsional)">
                                    <div class="flex items-center gap-2">
                                        <input class="form-input text-xs flex-1" :name="`co_authors[${index}][affiliation]`" x-model="co.affiliation" placeholder="Afiliasi (opsional)">
                                        <button type="button" @click="coAuthors.splice(index, 1)" class="text-rose-600 font-bold px-2">✕</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-end gap-3 pt-3">
                            <button type="button" @click="openEdit = false" class="btn btn-ghost">Batal</button>
                            <button class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>

                <!-- Live Editorial Checklist Monitoring (Only visible after editor requests revision / sends feedback) -->
                @if(in_array($submission->status, [\App\Enums\SubmissionStatus::NeedsAuthorCorrection, \App\Enums\SubmissionStatus::WaitingAuthorRevision], true) || $submission->feedback->isNotEmpty())
                    @if($submission->conference->checklistTemplates->isNotEmpty())
                        <div class="card p-6">
                            <h2 class="text-lg font-black text-navy">Monitoring Checklist Editorial (Live)</h2>
                            <p class="text-xs text-muted mt-1">Status kelengkapan format paper berdasarkan pemeriksaan tim editorial.</p>
                            <div class="mt-4 space-y-2">
                                @foreach($submission->conference->checklistTemplates as $tmpl)
                                    @foreach($tmpl->items as $item)
                                        @php $res = isset($checklistResults) ? $checklistResults->get($item->id) : null; @endphp
                                        <div class="flex items-start justify-between gap-3 p-3 rounded-xl border {{ $res?->is_checked ? 'bg-emerald-50/50 border-emerald-200' : 'bg-rose-50/50 border-rose-200' }}">
                                            <div>
                                                <p class="text-xs font-extrabold {{ $res?->is_checked ? 'text-emerald-900' : 'text-rose-900' }}">{{ $item->title }}</p>
                                                @if($item->description)
                                                    <p class="text-[11px] text-slate-600 mt-0.5">{{ $item->description }}</p>
                                                @endif
                                                @if($res?->note)
                                                    <p class="text-[11px] font-semibold text-slate-800 mt-1">Catatan: {{ $res->note }}</p>
                                                @endif
                                            </div>
                                            <span class="badge {{ $res?->is_checked ? 'badge-success' : 'badge-danger' }} shrink-0">
                                                {{ $res?->is_checked ? '✓ OK' : '✕ Perlu Perbaikan' }}
                                            </span>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                @if ($submission->feedback->isNotEmpty())
                    <div class="card p-6">
                        <h2 class="text-lg font-black text-navy">Catatan dari tim</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($submission->feedback as $item)
                                <div class="rounded-xl bg-warm p-4 text-sm leading-6">{!! nl2br(e($item->body)) !!}<p class="mt-2 text-xs text-muted">{{ $item->created_at->timezone($submission->conference->timezone)->format('d M Y H:i') }}</p></div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @foreach($submission->uploadAttempts->where('source','author')->where('status','failed') as $attempt)<div class="card border-danger/20 p-5"><p class="font-bold text-danger">Upload {{ $attempt->original_name }} gagal</p><p class="mt-1 text-xs text-muted">{{ Str::limit($attempt->error,160) }}</p><form class="mt-3" method="POST" action="{{ route('author.uploads.retry',[$token,$attempt]) }}">@csrf<button class="btn btn-secondary">Coba lagi tanpa pilih file</button></form></div>@endforeach

                @if (in_array($submission->status, [\App\Enums\SubmissionStatus::NeedsAuthorCorrection, \App\Enums\SubmissionStatus::WaitingAuthorRevision], true))
                    <form method="POST" action="{{ route('author.revision', $token) }}" enctype="multipart/form-data" class="card p-6">
                        @csrf
                        <h2 class="text-lg font-black text-navy">Unggah revisi</h2>
                        <label class="mt-5 block"><span class="form-label">File editable baru *</span><input class="form-input py-3" type="file" name="paper_file" accept=".docx,.zip" required><span class="mt-2 block text-xs text-muted">Gunakan DOCX atau ZIP yang berisi seluruh source LaTeX.</span></label>
                        <label class="mt-4 block"><span class="form-label">PDF Petunjuk Revisi / Response Form (Opsional)</span><input class="form-input py-2 text-xs" type="file" name="guidance_pdf" accept=".pdf"><span class="mt-1 block text-xs text-muted">Upload berkas PDF penjelasan revisi/response form dari author jika ada.</span></label>
                        <label class="mt-5 block"><span class="form-label">Catatan perubahan</span><textarea class="form-input min-h-24 py-3" name="notes"></textarea></label>
                        <button class="btn btn-primary mt-5">Kirim revisi</button>
                    </form>
                @endif

                <div class="card overflow-hidden">
                    <div class="p-6"><h2 class="text-lg font-black text-navy">Riwayat file</h2></div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Versi</th><th>File</th><th>Sumber</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($submission->files as $file)
                                    <tr><td>v{{ $file->version_number }}</td><td><p class="font-bold text-navy">{{ $file->label }}</p><p class="text-xs text-muted">{{ $file->original_name }}</p></td><td>{{ ucfirst($file->source) }}</td><td><a class="font-bold text-orange" href="{{ route('author.files.download', [$token, $file]) }}">Download</a></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <aside class="card h-fit p-6">
                <h2 class="text-lg font-black text-navy">Timeline</h2>
                <ol class="mt-5 space-y-5 border-l-2 border-navy/10 pl-5">
                    @foreach ($submission->statusHistory as $history)
                        <li><span class="-ml-[27px] mr-3 inline-block size-3 rounded-full bg-orange ring-4 ring-warm"></span><span class="text-sm font-bold text-navy">{{ $history->to_status->label() }}</span><p class="mt-1 text-xs text-muted">{{ $history->created_at->timezone($submission->conference->timezone)->format('d M Y H:i') }}</p></li>
                    @endforeach
                </ol>
            </aside>
        </div>
    </div>
</x-layouts.public>
