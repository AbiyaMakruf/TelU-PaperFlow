@props(['conference' => null, 'active' => 'overview'])

@php
    $c = ($conference instanceof \App\Models\Conference && $conference->exists)
        ? $conference
        : (\App\Models\Conference::find(session('active_conference_id')) ?? \App\Models\Conference::orderBy('name')->first());
@endphp

@if($c)
<div class="space-y-6">
    <a href="{{ route('conferences.index') }}" class="back-link">&larr; All Conferences</a>

    <!-- Conference Header & Direct Action Buttons -->
    <div class="card p-5 sm:p-6 bg-white border border-navy/10 shadow-sm">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="page-title text-xl sm:text-2xl font-black text-navy truncate">{{ $c->name }}</h1>
                    <span class="badge badge-{{ ($c->status ?? null) === \App\Enums\ConferenceStatus::Active ? 'success' : 'neutral' }} text-xs font-extrabold px-3 py-1">
                        {{ $c->status?->label() ?? 'Active' }}
                    </span>
                </div>
                <p class="text-xs text-muted mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span>Timezone: <strong class="text-navy font-bold">{{ $c->timezone }}</strong></span>
                    <span>·</span>
                    <span>Storage Disk: <strong class="text-navy font-bold uppercase">{{ $c->storage_provider }}</strong></span>
                </p>
            </div>

            <!-- Direct Public Form & Landing Page Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <a href="{{ route('public.submission.show', $c->slug ?: $c->id) }}" target="_blank" rel="noopener" class="btn text-xs py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold shadow-sm transition flex items-center gap-1.5">
                    📝 Open Submission Form ↗
                </a>
                <a href="{{ route('public.conference.show', $c->slug ?: $c->id) }}" target="_blank" rel="noopener" class="btn btn-secondary text-xs py-2 px-4 font-bold text-navy hover:text-orange transition flex items-center gap-1.5">
                    🌐 View Landing Page ↗
                </a>
            </div>
        </div>

        @can('view', $c)
            <!-- Conference Sub-Navigation Action Bar -->
            <div class="mt-5 pt-4 border-t border-navy/10">
                <div class="flex flex-wrap items-center gap-1.5">
                    @can('update', $c)
                        <a href="{{ route('conferences.show', $c) }}" class="rounded-xl px-3.5 py-2 text-xs font-bold transition flex items-center gap-1.5 {{ $active === 'overview' ? 'bg-navy text-white shadow-sm font-extrabold' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                            <span>📊</span> <span>Overview</span>
                        </a>
                        <a href="{{ route('conferences.form.edit', $c) }}" class="rounded-xl px-3.5 py-2 text-xs font-bold transition flex items-center gap-1.5 {{ $active === 'form' ? 'bg-navy text-white shadow-sm font-extrabold' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                            <span>🎨</span> <span>Form Builder</span>
                        </a>
                        <a href="{{ route('conferences.drive.show', $c) }}" class="rounded-xl px-3.5 py-2 text-xs font-bold transition flex items-center gap-1.5 {{ $active === 'storage' ? 'bg-navy text-white shadow-sm font-extrabold' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                            <span>☁️</span> <span>File Storage</span>
                        </a>
                        <a href="{{ route('conferences.checklists.edit', $c) }}" class="rounded-xl px-3.5 py-2 text-xs font-bold transition flex items-center gap-1.5 {{ $active === 'checklists' ? 'bg-navy text-white shadow-sm font-extrabold' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                            <span>✅</span> <span>IEEE Checklist</span>
                        </a>
                        <a href="{{ route('conferences.email-templates.edit', $c) }}" class="rounded-xl px-3.5 py-2 text-xs font-bold transition flex items-center gap-1.5 {{ $active === 'templates' ? 'bg-navy text-white shadow-sm font-extrabold' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                            <span>📧</span> <span>Email Templates</span>
                        </a>
                        <a href="{{ route('conferences.members.index', $c) }}" class="rounded-xl px-3.5 py-2 text-xs font-bold transition flex items-center gap-1.5 {{ $active === 'members' ? 'bg-navy text-white shadow-sm font-extrabold' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                            <span>👥</span> <span>Team Members</span>
                        </a>
                    @endcan

                    <a href="{{ route('conferences.edas-reconciliation.index', $c) }}" class="rounded-xl px-3.5 py-2 text-xs font-bold transition flex items-center gap-1.5 {{ $active === 'edas' ? 'bg-navy text-white shadow-sm font-extrabold' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                        <span>🔄</span> <span>EDAS Reconciliation</span>
                    </a>

                    @can('update', $c)
                        <a href="{{ route('conferences.edit', $c) }}" class="rounded-xl px-3.5 py-2 text-xs font-bold transition flex items-center gap-1.5 {{ $active === 'settings' ? 'bg-navy text-white shadow-sm font-extrabold' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                            <span>⚙️</span> <span>Settings</span>
                        </a>
                    @endcan
                </div>
            </div>
        @endcan
    </div>
</div>
@endif
