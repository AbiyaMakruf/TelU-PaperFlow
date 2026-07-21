<x-layouts.app title="Dashboard · Paperflow" heading="Dashboard">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-bold uppercase tracking-[.18em] text-orange">Ringkasan workspace</p>
            <h1 class="mt-2 text-3xl font-black text-navy">Selamat datang, {{ str(auth()->user()->name)->before(' ') }}.</h1>
            <p class="mt-2 text-sm text-muted">Lihat paper yang membutuhkan perhatian Anda.</p>
        </div>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Total paper', $stats['total'], 'bg-navy'], ['Sedang diproses', $stats['active'], 'bg-orange'], ['Menunggu author', $stats['waiting'], 'bg-warning'], ['Selesai', $stats['done'], 'bg-success']] as $stat)
            <div class="card p-5"><div class="flex items-center justify-between"><p class="text-sm font-semibold text-muted">{{ $stat[0] }}</p><span class="size-2.5 rounded-full {{ $stat[2] }}"></span></div><p class="mt-3 text-3xl font-black text-navy">{{ number_format($stat[1]) }}</p></div>
        @endforeach
    </div>
    <div class="mt-8 grid gap-6 xl:grid-cols-[1.4fr_.6fr]">
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-navy/10 px-6 py-5"><div><h2 class="font-extrabold text-navy">Paper terbaru</h2><p class="mt-1 text-xs text-muted">Submission dan assignment terakhir</p></div></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Paper</th><th>Conference</th><th>Status</th><th>PIC</th></tr></thead>
                    <tbody>
                    @forelse ($recentSubmissions as $submission)
                        <tr><td><a href="{{ route('submissions.show', $submission) }}" class="font-bold text-navy hover:text-orange">{{ $submission->paper_id ?: $submission->paper_code ?: 'Belum bernomor' }}</a><p class="max-w-xs truncate text-xs text-muted">{{ $submission->title }}</p></td><td>{{ $submission->conference->name }}</td><td><x-status-badge :status="$submission->status" /></td><td>{{ $submission->editor?->name ?? $submission->reviewer?->name ?? 'Belum di-assign' }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-12 text-center text-muted">Belum ada paper pada workspace Anda.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card p-6">
            <h2 class="font-extrabold text-navy">Conference</h2>
            <div class="mt-5 space-y-3">
                @forelse ($conferences as $conference)
                    <div class="rounded-xl border border-navy/10 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0"><a class="truncate font-bold text-navy hover:text-orange" href="{{ route('conferences.show', $conference) }}">{{ $conference->name }}</a><p class="mt-1 text-xs text-muted">{{ $conference->submissions_count }} paper · /{{ $conference->slug }}</p></div>
                            <span class="badge badge-primary">{{ $conference->status->label() }}</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 border-t border-navy/8 pt-3">
                            <a class="btn btn-ghost px-3 py-2 text-xs" href="{{ route('conferences.show', $conference) }}">Buka conference</a>
                            @if ($conference->has_published_form && $conference->isSubmissionOpen())
                                <a class="btn btn-secondary px-3 py-2 text-xs" href="{{ route('public.submission.show', $conference->slug) }}" target="_blank" rel="noopener">Buka form ↗</a>
                            @else
                                <span class="inline-flex items-center px-3 py-2 text-xs font-semibold text-muted">Form belum aktif</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted">Belum memiliki akses conference.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
