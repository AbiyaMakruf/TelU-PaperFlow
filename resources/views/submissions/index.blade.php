<x-layouts.app title="Paper" heading="Paper">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div><p class="eyebrow">Editorial pipeline</p><h1 class="page-title">Semua paper</h1><p class="page-subtitle">Pantau submission, PIC, dan tahap pemeriksaan dalam satu tabel.</p></div>
        <a class="btn btn-secondary" href="{{ route('submissions.export', request()->query()) }}">Export laporan lengkap</a>
    </div>

    <form class="card mt-7 grid gap-4 p-5 md:grid-cols-4">
        <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Cari kode, judul, atau author...">
        <select class="form-input" name="conference"><option value="">Semua conference</option>@foreach($conferences as $conference)<option value="{{ $conference->id }}" @selected(request('conference') === $conference->id)>{{ $conference->name }}</option>@endforeach</select>
        <select class="form-input" name="status"><option value="">Semua status</option>@foreach(\App\Enums\SubmissionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
        <select class="form-input" name="editor"><option value="">Semua editor</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((string)request('editor')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select>
        <select class="form-input" name="reviewer"><option value="">Semua reviewer</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((string)request('reviewer')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select>
        <label><span class="form-label">Masuk dari</span><input class="form-input" type="date" name="date_from" value="{{ request('date_from') }}"></label><label><span class="form-label">Sampai</span><input class="form-input" type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <label class="check-row"><input type="checkbox" name="overdue" value="1" @checked(request('overdue'))><span>Hanya overdue</span></label>
        <button class="btn btn-primary">Filter</button>
    </form>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Paper</th><th>Conference</th><th>Status</th><th>PIC</th><th>Masuk</th></tr></thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        <tr>
                            <td><a href="{{ route('submissions.show', $submission) }}" class="font-black text-navy hover:text-orange">{{ $submission->paper_code }}</a><p class="mt-1 max-w-lg truncate text-xs text-muted">{{ $submission->title }}</p><p class="mt-1 text-xs">{{ $submission->corresponding_author_name }}</p></td>
                            <td>{{ $submission->conference->name }}</td>
                            <td><span class="badge badge-{{ $submission->status->color() }}">{{ $submission->status->label() }}</span></td>
                            <td><p>{{ $submission->editor?->name ?? 'Belum ada editor' }}</p><p class="mt-1 text-xs text-muted">Reviewer: {{ $submission->reviewer?->name ?? '-' }}</p></td>
                            <td>{{ $submission->submitted_at?->format('d M Y') ?? '-' }}@if($submission->deadline_at)<p class="mt-1 text-xs {{ $submission->isOverdue() ? 'text-danger font-bold':'text-muted' }}">Deadline {{ $submission->deadline_at->format('d M Y') }}</p>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-12 text-center text-muted">Belum ada paper yang sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($submissions->hasPages())<div class="border-t border-navy/10 p-5">{{ $submissions->links() }}</div>@endif
    </div>
</x-layouts.app>
