<x-layouts.app :title="$conference->name.' · Paperflow'" :heading="$conference->name">
    <x-conference-header :conference="$conference" active="overview" />

    @if($conference->isGoogleFormMode())
        <div class="mt-6 rounded-2xl border border-purple-200 bg-purple-50/60 p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-purple-200/70 pb-4">
                <div>
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-purple-300 bg-purple-100 px-3 py-1 text-xs font-black text-purple-900">
                        📑 Google Form External Sync Active
                    </span>
                    <h2 class="mt-2 text-lg font-black text-navy">Google Form & Spreadsheet Setup Instructions</h2>
                </div>
                @can('update', $conference)
                    <a href="{{ route('conferences.edit', $conference) }}" class="btn btn-secondary text-xs font-bold shrink-0 self-start sm:self-auto">
                        ⚙️ Edit Column Mapping
                    </a>
                @endcan
            </div>
            
            <p class="text-xs text-slate-700 leading-relaxed">
                Follow these required setup steps to automatically sync incoming Google Form entries into Paperflow in real-time:
            </p>

            <ol class="list-decimal list-inside text-xs text-navy font-medium space-y-2 bg-white p-4 rounded-xl border border-purple-200">
                <li>Open your conference's Google Form or connected Google Sheets spreadsheet.</li>
                <li>Go to <strong>Extensions &gt; Apps Script</strong> (or click <strong>&vellip; &gt; Script Editor</strong>).</li>
                <li>Copy and paste the Paperflow Apps Script integration code into the editor.</li>
                <li>
                    Set the Webhook Endpoint URL to:
                    <code class="font-mono bg-purple-100/60 px-2 py-0.5 rounded border border-purple-300 text-purple-950 font-bold select-all">{{ url('/api/webhooks/google-form/'.$conference->slug) }}</code>
                </li>
                <li>
                    Set the Secret Token header (<code class="font-mono text-navy font-bold">X-Paperflow-Secret</code>) to:
                    <code class="font-mono bg-purple-100/60 px-2 py-0.5 rounded border border-purple-300 text-purple-950 font-bold select-all">{{ env('GOOGLE_FORM_WEBHOOK_SECRET', 'paperflow_webhook_secret_key') }}</code>
                </li>
                <li>Save the script and add an <strong>On form submit</strong> trigger under Apps Script Triggers (&num;1 Event Source: <em>From form / From spreadsheet</em>).</li>
            </ol>
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
