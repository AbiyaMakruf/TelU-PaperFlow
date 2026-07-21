<x-layouts.app title="Paper" heading="Paper">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div><p class="eyebrow">Editorial pipeline</p><h1 class="page-title">Semua paper</h1><p class="page-subtitle">Pantau submission, PIC, dan tahap pemeriksaan dalam satu tabel.</p></div>
        <a class="btn btn-secondary w-full sm:w-auto" href="{{ route('submissions.export', request()->query()) }}">Export laporan lengkap</a>
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

    @php
        $sortUrl = fn (string $column) => route('submissions.index', array_merge(request()->query(), [
            'sort' => $column,
            'direction' => request('sort') === $column && request('direction', 'desc') === 'asc' ? 'desc' : 'asc',
        ]));
    @endphp
    <div class="card mt-6 overflow-hidden">
        <div class="hidden overflow-x-auto md:block">
            <table class="data-table">
                <thead><tr><th><a href="{{ $sortUrl('paper_id') }}">Paper ID ↕</a></th><th><a href="{{ $sortUrl('title') }}">Title ↕</a></th><th><a href="{{ $sortUrl('pic') }}">PIC ↕</a></th><th><a href="{{ $sortUrl('status') }}">Status ↕</a></th><th><a href="{{ $sortUrl('submitted_at') }}">Masuk ↕</a></th><th>Detail</th></tr></thead>
                    @forelse ($submissions as $submission)
                        <tbody x-data="{ open: false }" class="border-b border-navy/8">
                        <tr>
                            <td><p class="font-black text-navy">{{ $submission->paper_id ?: $submission->paper_code }}</p><p class="mt-1 text-xs text-muted">{{ $submission->conference->name }}</p></td>
                            <td><p class="max-w-lg font-bold text-navy">{{ $submission->title }}</p></td>
                            <td><p>{{ $submission->editor?->name ?? 'Belum ada editor' }}</p><p class="mt-1 text-xs text-muted">Reviewer: {{ $submission->reviewer?->name ?? '-' }}</p></td>
                            <td><span class="badge badge-{{ $submission->status->color() }}">{{ $submission->status->label() }}</span></td>
                            <td>{{ $submission->submitted_at?->format('d M Y') ?? '-' }}@if($submission->deadline_at)<p class="mt-1 text-xs {{ $submission->isOverdue() ? 'text-danger font-bold':'text-muted' }}">Deadline {{ $submission->deadline_at->format('d M Y') }}</p>@endif</td>
                            <td><button type="button" class="font-bold text-orange" x-on:click="open = !open" x-text="open ? 'Tutup −' : 'Lihat +'">Lihat +</button></td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="6" class="bg-warm/70 p-5">
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
                        <tbody><tr><td colspan="6" class="py-12 text-center text-muted">Belum ada paper yang sesuai filter.</td></tr></tbody>
                    @endforelse
            </table>
        </div>
        <div class="divide-y divide-navy/10 md:hidden">
            @forelse($submissions as $submission)
                <article x-data="{ open: false }" class="p-4">
                    <button type="button" class="w-full text-left" x-on:click="open = !open">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-black text-navy">{{ $submission->paper_id ?: $submission->paper_code }}</p><p class="mt-1 line-clamp-2 text-sm font-semibold">{{ $submission->title }}</p></div><span class="badge badge-{{ $submission->status->color() }} shrink-0">{{ $submission->status->label() }}</span></div>
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
</x-layouts.app>
