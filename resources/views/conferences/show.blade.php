<x-layouts.app :title="$conference->name.' · Paperflow'" :heading="$conference->name">
    <x-conference-header :conference="$conference" active="overview" />

    @if($conference->isGoogleFormMode())
        @php
            $webhookUrl = url('/api/webhooks/google-form/'.$conference->slug);
            $secretToken = env('GOOGLE_FORM_WEBHOOK_SECRET', 'paperflow_webhook_secret_key');
            $appsScriptCode = "/**\n * PAPERFLOW REAL-TIME GOOGLE FORM WEBHOOK INTEGRATION\n */\nconst PAPERFLOW_WEBHOOK_URL = \"{$webhookUrl}\";\nconst SECRET_TOKEN = \"{$secretToken}\";\n\nfunction onFormSubmit(e) {\n  try {\n    let payload = {};\n    if (e && e.namedValues) {\n      for (let key in e.namedValues) {\n        payload[key.trim()] = e.namedValues[key][0] || \"\";\n      }\n    } else if (e && e.response) {\n      let itemResponses = e.response.getItemResponses();\n      for (let i = 0; i < itemResponses.length; i++) {\n        let itemResponse = itemResponses[i];\n        let title = itemResponse.getItem().getTitle().trim();\n        let response = itemResponse.getResponse();\n        payload[title] = Array.isArray(response) ? response.join(\", \") : response;\n      }\n    } else {\n      return;\n    }\n\n    let options = {\n      \"method\": \"post\",\n      \"contentType\": \"application/json\",\n      \"headers\": {\n        \"X-Paperflow-Secret\": SECRET_TOKEN\n      },\n      \"payload\": JSON.stringify(payload),\n      \"muteHttpExceptions\": true\n    };\n\n    UrlFetchApp.fetch(PAPERFLOW_WEBHOOK_URL, options);\n  } catch (error) {\n    Logger.log(\"Paperflow Webhook Error: \" + error.toString());\n  }\n}";
        @endphp
        <div class="mt-6 rounded-2xl border border-purple-200 bg-purple-50/60 p-6 shadow-xs space-y-4" x-data="{ copiedCode: false }">
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

            <ol class="list-decimal list-inside text-xs text-navy font-medium space-y-3 bg-white p-4 rounded-xl border border-purple-200">
                <li>Open your conference's Google Form (or connected Google Sheets spreadsheet).</li>
                <li>Go to <strong>Extensions &gt; Apps Script</strong> (or click <strong>&vellip; &gt; Script Editor</strong>) to open the <code class="font-mono text-purple-900 bg-purple-100 px-1.5 py-0.5 rounded">script.google.com</code> editor.</li>
                <li>
                    Clear any default code inside <code class="font-mono bg-purple-100/60 px-1.5 py-0.5 rounded text-purple-950 font-bold">Code.gs</code>, then click <strong>"📋 Copy Complete Apps Script Code"</strong> below and paste it into the editor:
                    <div class="mt-2.5 bg-navy text-slate-100 p-4 rounded-xl font-mono text-[11px] leading-relaxed relative overflow-x-auto border border-navy/20 shadow-xs">
                        <div class="flex items-center justify-between gap-2 mb-2 pb-2 border-b border-white/10 flex-wrap">
                            <span class="text-xs text-orange font-bold font-sans">Google Apps Script Integration Code (Code.gs)</span>
                            <button type="button" @click="navigator.clipboard.writeText(`{{ $appsScriptCode }}`); copiedCode = true; setTimeout(() => copiedCode = false, 2000)" class="btn text-xs py-1 px-3 bg-orange hover:bg-orange-dark text-white font-sans font-bold rounded-lg transition shrink-0 shadow-2xs">
                                <span x-text="copiedCode ? 'Copied to Clipboard! ✓' : '📋 Copy Complete Apps Script Code'">📋 Copy Complete Apps Script Code</span>
                            </button>
                        </div>
                        <pre class="whitespace-pre overflow-x-auto text-[11px] text-emerald-300">{{ $appsScriptCode }}</pre>
                    </div>
                </li>
                <li>Click <strong>Save 💾</strong> (or press <kbd class="px-1.5 py-0.5 bg-white border border-navy/20 rounded text-[10px]">Ctrl+S</kbd> / <kbd class="px-1.5 py-0.5 bg-white border border-navy/20 rounded text-[10px]">Cmd+S</kbd>).</li>
                <li>
                    On the left sidebar menu of <code class="font-mono text-purple-900 bg-purple-100 px-1.5 py-0.5 rounded">script.google.com</code>, click <strong>Triggers ⏰</strong> (alarm clock icon) &rarr; Click <strong>+ Add Trigger</strong> (at bottom right):
                    <ul class="mt-1.5 ml-5 list-disc space-y-1 text-xs text-navy/80 font-normal">
                        <li>Choose function to run: <strong><code class="font-mono text-navy font-bold">onFormSubmit</code></strong></li>
                        <li>Select event source: <strong><code class="font-mono text-navy font-bold">From form</code></strong> (or <em>From spreadsheet</em>)</li>
                        <li>Select event type: <strong><code class="font-mono text-navy font-bold">On form submit</code></strong></li>
                    </ul>
                </li>
                <li>Click <strong>Save</strong> and grant account authorization when prompted. All submissions will now sync instantly into Paperflow!</li>
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
