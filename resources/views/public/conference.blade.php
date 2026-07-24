<x-layouts.public :title="$conference->name">
    <div class="mx-auto max-w-4xl">
        <section class="card overflow-hidden" style="--brand-primary:{{ $conference->brandPrimary() }};--brand-accent:{{ $conference->brandAccent() }}">
            <div class="px-6 py-10 text-white sm:px-10" style="background:var(--brand-primary)">
                @if($conference->brandLogoUrl())
                    <img class="mb-6 max-h-20 max-w-56 object-contain" src="{{ $conference->brandLogoUrl() }}" alt="Logo {{ $conference->name }}">
                @endif
                <p class="eyebrow !text-orange">Paperflow Conference</p>
                <h1 class="mt-3 text-3xl font-black sm:text-4xl">{{ $conference->name }}</h1>
                @if($conference->description)
                    <p class="mt-4 max-w-2xl text-white/75">{{ $conference->description }}</p>
                @endif
                @if($conference->settings['brand_tagline'] ?? null)
                    <p class="mt-3 font-bold" style="color:var(--brand-accent)">{{ $conference->settings['brand_tagline'] }}</p>
                @endif
            </div>
            <div class="p-6 sm:p-10">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-muted">Conference Status</p>
                        <p class="mt-1 font-black text-navy">{{ $conference->status->label() }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-muted">Submission Deadline</p>
                        <p class="mt-1 font-black text-navy">{{ $conference->submission_closes_at?->timezone($conference->timezone)->format('d M Y H:i') ?? 'Not specified' }}</p>
                    </div>
                </div>
                <div class="mt-8 border-t border-navy/10 pt-6">
                    @if($formAvailable && $storageReady)
                        <a href="{{ route('public.submission.show', $conference) }}" class="btn text-white font-bold" style="background:var(--brand-primary)">Submit Manuscript &rarr;</a>
                    @elseif(!$formAvailable)
                        <p class="font-bold text-muted">The manuscript submission form is not currently open.</p>
                    @else
                        <p class="font-bold text-muted">Submissions are temporarily unavailable while conference storage is being configured.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-layouts.public>
