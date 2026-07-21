<x-layouts.app title="Paper" heading="Paper">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end" x-data="{ selected: [], bulkModal: null }">
        <div>
            <p class="eyebrow">Editorial pipeline</p>
            <h1 class="page-title">Semua paper</h1>
            <p class="page-subtitle">Pantau submission, PIC, dan tahap pemeriksaan dalam satu tabel.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <div x-data="{ open: false }" class="relative w-full sm:w-auto">
                <button type="button" @click="open = !open" class="btn btn-secondary w-full sm:w-auto">
                    Export Laporan ▾
                </button>
                <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-48 rounded-xl bg-white p-2 shadow-xl border border-slate-200 z-50">
                    <a class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg" href="{{ route('submissions.export', array_merge(request()->query(), ['format' => 'csv'])) }}">CSV File (.csv)</a>
                    <a class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg" href="{{ route('submissions.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}">Microsoft Excel (.xlsx)</a>
                    <a class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg" target="_blank" href="{{ route('submissions.export', array_merge(request()->query(), ['format' => 'pdf'])) }}">PDF Report (Print)</a>
                </div>
            </div>
        </div>
    </div>

    <form class="card mt-7 grid gap-4 p-5 md:grid-cols-4">
        <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Cari kode, judul, atau author...">
        <select class="form-input" name="conference"><option value="">Semua conference</option>@foreach($conferences as $conference)<option value="{{ $conference->id }}" @selected(request('conference') === $conference->id)>{{ $conference->name }}</option>@endforeach</select>
        <select class="form-input" name="status"><option value="">Semua status</option>@foreach(\App\Enums\SubmissionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
        <select class="form-input" name="editor"><option value="">Semua editor</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((string)request('editor')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select>
        <select class="form-input" name="reviewer"><option value="">Semua reviewer</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((string)request('reviewer')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select>
        <label><span class="form-label">Masuk dari</span><input class="form-input" type="date" name="date_from" value="{{ request('date_from') }}"></label><label><span class="form-label">Sampai</span><input class="form-input" type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <label class="check-row"><input type="checkbox" name="overdue" value="1" @checked(request('overdue'))><span>Hanya overdue</span></label>
        <button class="btn btn-primary md:self-end">Terapkan filter</button>
        @if(request()->query())<a class="btn btn-ghost md:self-end" href="{{ route('submissions.index') }}">Reset</a>@endif
    </form>

    <div x-data="{ selected: [], bulkAction: '' }">
        <!-- Floating Bulk Bar -->
        <div x-show="selected.length > 0" x-cloak class="sticky top-4 z-40 my-4 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-slate-900 p-4 text-white shadow-2xl">
            <div class="text-sm font-bold">
                <span x-text="selected.length"></span> paper dipilih
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" @click="$dispatch('open-bulk-assign')" class="btn btn-primary text-xs py-1.5 px-3">
                    Bulk Assign PIC & Deadline
                </button>
                <button type="button" @click="$dispatch('open-bulk-status')" class="btn btn-secondary text-xs py-1.5 px-3">
                    Bulk Update Status
                </button>
                <button type="button" @click="selected = []" class="text-xs text-slate-400 hover:text-white underline">
                    Batal pilih
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
            <div class="hidden overflow-x-auto md:block">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10">
                                <input type="checkbox" @change="selected = $event.target.checked ? [{{ $submissions->pluck('id')->map(fn($id) => "'$id'")->join(',') }}] : []">
                            </th>
                            <th><a href="{{ $sortUrl('paper_id') }}">Paper ID ↕</a></th>
                            <th><a href="{{ $sortUrl('title') }}">Title ↕</a></th>
                            <th><a href="{{ $sortUrl('pic') }}">PIC ↕</a></th>
                            <th><a href="{{ $sortUrl('status') }}">Status ↕</a></th>
                            <th><a href="{{ $sortUrl('submitted_at') }}">Masuk ↕</a></th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    @forelse ($submissions as $submission)
                        <tbody x-data="{ open: false }" class="border-b border-navy/8">
                        <tr>
                            <td>
                                <input type="checkbox" value="{{ $submission->id }}" x-model="selected">
                            </td>
                            <td>
                                <p class="font-black text-navy">{{ $submission->paper_id ?: $submission->paper_code }}</p>
                                <p class="mt-1 text-xs text-muted">{{ $submission->conference->name }}</p>
                                @if($submission->is_flagged_duplicate)
                                    <span class="mt-1 inline-block rounded bg-rose-100 px-2 py-0.5 text-[10px] font-extrabold text-rose-700" title="{{ $submission->duplicate_notes }}">⚠️ Potensi Duplikat</span>
                                @endif
                            </td>
                            <td><p class="max-w-lg font-bold text-navy">{{ $submission->title }}</p></td>
                            <td><p>{{ $submission->editor?->name ?? 'Belum ada editor' }}</p><p class="mt-1 text-xs text-muted">Reviewer: {{ $submission->reviewer?->name ?? '-' }}</p></td>
                            <td><span class="badge badge-{{ $submission->status->color() }}">{{ $submission->status->label() }}</span></td>
                            <td>{{ $submission->submitted_at?->format('d M Y') ?? '-' }}@if($submission->deadline_at)<p class="mt-1 text-xs {{ $submission->isOverdue() ? 'text-danger font-bold':'text-muted' }}">Deadline {{ $submission->deadline_at->format('d M Y') }}</p>@endif</td>
                            <td><button type="button" class="font-bold text-orange" x-on:click="open = !open" x-text="open ? 'Tutup −' : 'Lihat +'">Lihat +</button></td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="7" class="bg-warm/70 p-5">
                                <div class="grid gap-4 text-sm md:grid-cols-4">
                                    <div><p class="form-label">Kode internal</p><p class="font-bold text-navy">{{ $submission->paper_code }}</p></div>
                                    <div><p class="form-label">Corresponding author</p><p class="font-bold text-navy">{{ $submission->corresponding_author_name }}</p><p class="text-muted">{{ $submission->corresponding_author_email }}</p></div>
                                    <div><p class="form-label">Format editable</p><p class="font-bold text-navy">{{ $submission->manuscript_format === 'latex' ? 'LaTeX (ZIP)' : ($submission->manuscript_format === 'docx' ? 'Microsoft Word (DOCX)' : 'Belum dikonfirmasi admin') }}</p></div>
                                    <div><p class="form-label">Jumlah author</p><p class="font-bold text-navy">{{ $submission->authors->count() }}</p></div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-3"><a href="{{ route('submissions.show', $submission) }}" class="btn btn-secondary px-4 py-2 text-xs">Buka detail lengkap</a>@if($submission->files->first())<span class="self-center text-xs text-muted">File: {{ $submission->files->first()->original_name }}</span>@endif</div>
                            </td>
                        </tr>
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="7" class="py-12 text-center text-muted">Belum ada paper yang sesuai filter.</td></tr></tbody>
                    @endforelse
                </table>
            </div>
            <div class="divide-y divide-navy/10 md:hidden">
                @forelse($submissions as $submission)
                    <article x-data="{ open: false }" class="p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <input type="checkbox" value="{{ $submission->id }}" x-model="selected">
                            <p class="truncate font-black text-navy">{{ $submission->paper_id ?: $submission->paper_code }}</p>
                            @if($submission->is_flagged_duplicate)
                                <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">⚠️ Duplikat</span>
                            @endif
                        </div>
                        <button type="button" class="w-full text-left" x-on:click="open = !open">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="mt-1 line-clamp-2 text-sm font-semibold">{{ $submission->title }}</p></div><span class="badge badge-{{ $submission->status->color() }} shrink-0">{{ $submission->status->label() }}</span></div>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-xs"><div><span class="text-muted">PIC editor</span><p class="mt-1 truncate font-bold">{{ $submission->editor?->name ?? 'Belum ada' }}</p></div><div><span class="text-muted">Masuk</span><p class="mt-1 font-bold">{{ $submission->submitted_at?->format('d M Y') ?? '-' }}</p></div></div>
                            <p class="mt-3 text-xs font-bold text-orange" x-text="open ? 'Tutup detail −' : 'Lihat detail +'">Lihat detail +</p>
                        </button>
                        <div x-cloak x-show="open" x-collapse class="mt-4 rounded-xl bg-warm p-4 text-sm">
                            <dl class="space-y-3"><div><dt class="text-xs font-bold text-muted">Conference</dt><dd class="mt-1 font-semibold">{{ $submission->conference->name }}</dd></div><div><dt class="text-xs font-bold text-muted">Corresponding author</dt><dd class="mt-1">{{ $submission->corresponding_author_name }}</dd></div><div><dt class="text-xs font-bold text-muted">Reviewer</dt><dd class="mt-1">{{ $submission->reviewer?->name ?? '-' }}</dd></div><div><dt class="text-xs font-bold text-muted">Format editable</dt><dd class="mt-1">{{ $submission->manuscript_format === 'latex' ? 'LaTeX (ZIP)' : ($submission->manuscript_format === 'docx' ? 'Microsoft Word (DOCX)' : 'Belum dikonfirmasi') }}</dd></div></dl>
                            <a href="{{ route('submissions.show', $submission) }}" class="btn btn-secondary mt-4 w-full">Buka detail lengkap</a>
                        </div>
                    </article>
                @empty
                    <p class="p-8 text-center text-sm text-muted">Belum ada paper yang sesuai filter.</p>
                @endforelse
            </div>
            @if($submissions->hasPages())<div class="border-t border-navy/10 p-5">{{ $submissions->links() }}</div>@endif
        </div>

        <!-- Bulk Assign Modal -->
        <div x-data="{ show: false }" @open-bulk-assign.window="show = true" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.away="show = false">
                <h3 class="text-lg font-black text-slate-900">Bulk Assign PIC & Deadline</h3>
                <p class="mt-1 text-xs text-slate-500">Terapkan assignment untuk <span x-text="selected.length"></span> paper terpilih.</p>
                <form method="POST" action="{{ route('submissions.bulk-assign') }}" class="mt-4 space-y-4">
                    @csrf
                    <template x-for="id in selected">
                        <input type="hidden" name="submission_ids[]" :value="id">
                    </template>
                    <div>
                        <label class="form-label">Tetapkan Editor PIC</label>
                        <select class="form-input" name="editor_id">
                            <option value="">-- Tetap / Tidak diubah --</option>
                            @foreach($staff as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Tetapkan Reviewer PIC</label>
                        <select class="form-input" name="reviewer_id">
                            <option value="">-- Tetap / Tidak diubah --</option>
                            @foreach($staff as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Format Naskah</label>
                        <select class="form-input" name="manuscript_format">
                            <option value="">-- Tidak diubah --</option>
                            <option value="docx">Microsoft Word (DOCX)</option>
                            <option value="latex">LaTeX (ZIP)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Deadline</label>
                        <input type="date" class="form-input" name="deadline_at">
                    </div>
                    <div>
                        <label class="form-label">Alasan Reassignment (jika mengganti PIC)</label>
                        <input type="text" class="form-input" name="reassignment_reason" placeholder="Contoh: Pembagian beban kerja tim">
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="show = false" class="btn btn-ghost">Batal</button>
                        <button class="btn btn-primary">Terapkan Massal</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Status Update Modal -->
        <div x-data="{ show: false }" @open-bulk-status.window="show = true" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.away="show = false">
                <h3 class="text-lg font-black text-slate-900">Bulk Update Status</h3>
                <p class="mt-1 text-xs text-slate-500">Perbarui status <span x-text="selected.length"></span> paper terpilih.</p>
                <form method="POST" action="{{ route('submissions.bulk-status') }}" class="mt-4 space-y-4">
                    @csrf
                    <template x-for="id in selected">
                        <input type="hidden" name="submission_ids[]" :value="id">
                    </template>
                    <div>
                        <label class="form-label">Aksi Massal</label>
                        <select class="form-input" name="action" required>
                            <option value="accept">Validasi & Siap Assign (Ready for Assignment)</option>
                            <option value="reject">Tolak Paper (Reject)</option>
                            <option value="withdraw">Tarik Paper (Withdraw)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Catatan Aksi</label>
                        <textarea class="form-input min-h-20" name="note" placeholder="Catatan opsional..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="show = false" class="btn btn-ghost">Batal</button>
                        <button class="btn btn-primary">Eksekusi Status Massal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
