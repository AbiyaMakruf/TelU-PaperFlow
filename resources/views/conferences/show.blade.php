<x-layouts.app :title="$conference->name.' · Paperflow'" :heading="$conference->name">
    <x-conference-header :conference="$conference" active="overview" />

    @if($conference->isGoogleFormMode())
        <div class="mt-6 rounded-2xl border border-purple-200 bg-purple-50/60 p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-purple-200/70 pb-4">
                <div>
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-purple-300 bg-purple-100 px-3 py-1 text-xs font-black text-purple-900">
                        📥 Smart CSV / Excel Import Active
                    </span>
                    <h2 class="mt-2 text-lg font-black text-navy">Google Form / Spreadsheet Import Instructions</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('submissions.index') }}" class="btn btn-primary text-xs font-bold shrink-0">
                        📥 Import CSV / Excel File
                    </a>
                    @can('update', $conference)
                        <a href="{{ route('conferences.edit', $conference) }}" class="btn btn-secondary text-xs font-bold shrink-0">
                            ⚙️ Edit Column Mapping
                        </a>
                    @endcan
                </div>
            </div>
            
            <p class="text-xs text-slate-700 leading-relaxed">
                This conference is configured for Google Form / Spreadsheet CSV Import. You can export submissions from Google Form as a CSV or Excel file and import them anytime. Paperflow automatically matches column headers and updates existing submissions safely without duplicate entries.
            </p>
        </div>
    @endif

    <div class="mt-6 grid gap-4 grid-cols-2 xl:grid-cols-4">
        <div class="card p-5 border border-navy/10">
            <p class="text-xs font-extrabold uppercase tracking-wider text-muted">Total Papers</p>
            <p class="mt-2 text-3xl font-black text-navy">{{ $conference->submissions_count }}</p>
        </div>
        <div class="card p-5 border border-navy/10">
            <p class="text-xs font-extrabold uppercase tracking-wider text-muted">New Submissions</p>
            <p class="mt-2 text-3xl font-black text-navy">{{ $statusCounts['submitted'] ?? 0 }}</p>
        </div>
        <div class="card p-5 border border-navy/10">
            <p class="text-xs font-extrabold uppercase tracking-wider text-muted">Editorial Review</p>
            <p class="mt-2 text-3xl font-black text-navy">{{ $statusCounts['editorial_review'] ?? 0 }}</p>
        </div>
        <div class="card p-5 border border-navy/10">
            <p class="text-xs font-extrabold uppercase tracking-wider text-muted">Completed (Done)</p>
            <p class="mt-2 text-3xl font-black text-emerald-600">{{ $statusCounts['done'] ?? 0 }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 grid-cols-1 xl:grid-cols-[1.2fr_.8fr]">
        <section class="card p-6">
            <h2 class="font-black text-navy text-lg border-b border-navy/8 pb-3">Conference Configuration</h2>
            <dl class="mt-5 grid gap-4 text-xs sm:text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted font-medium text-xs">Timezone</dt>
                    <dd class="mt-1 font-extrabold text-navy">{{ $conference->timezone }}</dd>
                </div>
                <div>
                    <dt class="text-muted font-medium text-xs">Submission Period</dt>
                    <dd class="mt-1 font-extrabold text-navy">
                        {{ $conference->submission_opens_at?->format('d M Y H:i') ?? 'No start limit' }} &ndash; {{ $conference->submission_closes_at?->format('d M Y H:i') ?? 'No end limit' }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-muted font-medium text-xs">Description</dt>
                    <dd class="mt-1 leading-relaxed text-slate-700 break-words">{{ $conference->description ?: 'No description provided.' }}</dd>
                </div>
            </dl>
        </section>

        <section class="card p-6">
            <div class="flex items-center justify-between border-b border-navy/8 pb-3">
                <h2 class="font-black text-navy text-lg">Active Staff Team</h2>
                @can('manageMembers', $conference)
                    <a href="{{ route('conferences.members.index', $conference) }}" class="text-xs font-extrabold text-orange hover:underline">Manage Team &rarr;</a>
                @endcan
            </div>
            <div class="mt-4 space-y-3">
                @foreach($conference->memberships->where('is_active', true)->take(6) as $membership)
                    <div class="flex items-center gap-3 p-2 rounded-xl bg-warm/60">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-navy text-sm font-bold text-white shadow-sm">
                            {{ strtoupper(substr($membership->user->name, 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold text-navy">{{ $membership->user->name }}</p>
                            <p class="text-[11px] text-muted">{{ $membership->role->label() }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    @can('update', $conference)
        <details class="card mt-6 p-6">
            <summary class="cursor-pointer font-black text-navy text-base select-none">Duplicate Conference</summary>
            <p class="mt-2 text-xs text-muted">Copy submission form, IEEE checklists, and email templates to a new conference.</p>
            <form method="POST" action="{{ route('conferences.duplicate', $conference) }}" class="mt-5 grid gap-4 grid-cols-1 sm:grid-cols-[1fr_220px_auto]">
                @csrf
                <input class="form-input text-xs" name="name" placeholder="New conference name" required>
                <input class="form-input text-xs" name="slug" placeholder="new-slug" required>
                <button class="btn btn-secondary text-xs font-extrabold">Duplicate Conference</button>
            </form>
        </details>
    @endcan
</x-layouts.app>
