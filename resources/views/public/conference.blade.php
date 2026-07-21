<x-layouts.public :title="$conference->name">
    <div class="mx-auto max-w-4xl">
        <section class="card overflow-hidden">
            <div class="bg-navy px-6 py-10 text-white sm:px-10">
                <p class="eyebrow !text-orange">Paperflow conference</p>
                <h1 class="mt-3 text-3xl font-black sm:text-4xl">{{ $conference->name }}</h1>
                @if($conference->description)<p class="mt-4 max-w-2xl text-white/75">{{ $conference->description }}</p>@endif
            </div>
            <div class="p-6 sm:p-10">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-muted">Status</p><p class="mt-1 font-black text-navy">{{ $conference->status->label() }}</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-muted">Batas submission</p><p class="mt-1 font-black text-navy">{{ $conference->submission_closes_at?->timezone($conference->timezone)->format('d M Y H:i') ?? 'Tidak ditentukan' }}</p></div>
                </div>
                <div class="mt-8 border-t border-navy/10 pt-6">
                    @if($formAvailable && $driveReady)
                        <a href="{{ route('public.submission.show', $conference) }}" class="btn btn-primary">Submit manuscript</a>
                    @elseif(!$formAvailable)
                        <p class="font-bold text-muted">Form submission belum dibuka atau sudah ditutup.</p>
                    @else
                        <p class="font-bold text-muted">Submission sementara belum tersedia karena penyimpanan Drive belum dihubungkan.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-layouts.public>
