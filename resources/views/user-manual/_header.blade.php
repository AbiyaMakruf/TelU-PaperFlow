@props(['activeRole' => 'author'])

<div class="space-y-6">
    <!-- Header Title -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-navy/10 pb-5">
        <div>
            <p class="eyebrow">Documentation &amp; User Manual Center</p>
            <h1 class="page-title leading-tight">Paperflow User Manual</h1>
            <p class="page-subtitle max-w-3xl mt-1">Comprehensive step-by-step guides, workflows, and feature references for every role in the Paperflow ecosystem.</p>
        </div>
        <div class="shrink-0">
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-secondary text-xs font-extrabold">&larr; Back to Dashboard</a>
            @else
                <a href="{{ route('home') }}" class="btn btn-secondary text-xs font-extrabold">&larr; Back to Home</a>
            @endauth
        </div>
    </div>

    <!-- Role Ecosystem Matrix Overview Card -->
    <details class="card overflow-hidden border-2 border-orange/30 bg-amber-50/20 max-w-full" open>
        <summary class="cursor-pointer p-4 sm:p-5 font-black text-navy text-sm sm:text-base flex items-center justify-between select-none">
            <span class="flex items-center gap-2">
                <span>🗺️</span> <span>Role Matrix &amp; Ecosystem Responsibility Distribution</span>
            </span>
            <span class="text-orange font-bold text-lg">+</span>
        </summary>
        <div class="border-t border-navy/10 p-4 sm:p-6 bg-white space-y-4 text-xs sm:text-sm text-slate-800">
            <p class="leading-relaxed text-muted">
                Paperflow assigns permissions across <strong>6 primary roles</strong> to ensure accountability and structured editorial workflows. You can review the role matrix below to understand permissions across the ecosystem:
            </p>
            <div class="overflow-x-auto min-w-0 max-w-full">
                <table class="data-table min-w-[640px] text-xs">
                    <thead>
                        <tr>
                            <th class="w-36">Role</th>
                            <th class="w-32">Access Level</th>
                            <th>Primary Functions &amp; Responsibilities</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="{{ $activeRole === 'author' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">👤 Author</td>
                            <td><span class="badge badge-success text-[10px]">Public (Open)</span></td>
                            <td class="leading-relaxed">Upload paper manuscripts (`.docx`/`.zip`), monitor Live Checklist results on the token portal (`/submission/access/{token}`), edit submission details, and upload revision files with optional PDF response forms.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'editorial' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">✍️ Editorial (Editor PIC)</td>
                            <td><span class="badge badge-primary text-[10px]">Authenticated Staff</span></td>
                            <td class="leading-relaxed">Check 16 standard IEEE formatting items, auto-generate revision feedback templates (`⚡ Unchecked Items`), communicate via Email &amp; WhatsApp, upload new file versions, and forward papers to Reviewers.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'reviewer' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">🔍 Reviewer (Reviewer PIC)</td>
                            <td><span class="badge badge-primary text-[10px]">Authenticated Staff</span></td>
                            <td class="leading-relaxed">Inspect Editor results, verify IEEE PDF eXpress status (`Passed`/`Failed`), manage EDAS error notes with quick preset buttons, return to editorial, and approve EDAS uploads.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'admin' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">🏛️ Conference Admin</td>
                            <td><span class="badge badge-warning text-[10px]">Authenticated Staff</span></td>
                            <td class="leading-relaxed">Configure conferences, build custom submission forms, customize email templates &amp; default CCs, select file storage (Supabase / Google Drive OAuth), manage team members, validate initial papers, and export CSV/XLSX/PDF reports.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'superadmin' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">👑 Superadmin</td>
                            <td><span class="badge badge-danger text-[10px]">Full System Access</span></td>
                            <td class="leading-relaxed">Create new user accounts, provision new conferences, monitor system database &amp; storage health (`/monitoring`), review audit logs, and impersonate user accounts.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'viewer' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">👁️ Viewer</td>
                            <td><span class="badge badge-neutral text-[10px]">Read-Only Staff</span></td>
                            <td class="leading-relaxed">Read-only access to inspect conference progress, paper lists, and staff PIC workload matrices without data modification rights.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </details>

    <!-- Role Manual Navigation Tabs -->
    <div class="card p-2 bg-white border border-navy/10">
        <div class="flex flex-wrap items-center gap-1.5 text-xs font-extrabold">
            <a href="{{ route('user-manual.author') }}" class="rounded-xl px-3.5 py-2 transition flex items-center gap-1.5 {{ $activeRole === 'author' ? 'bg-orange text-white shadow-sm font-black' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                <span>👤</span> <span>Author (Public)</span>
            </a>

            @auth
                <a href="{{ route('user-manual.show', 'editorial') }}" class="rounded-xl px-3.5 py-2 transition flex items-center gap-1.5 {{ $activeRole === 'editorial' ? 'bg-navy text-white shadow-sm font-black' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                    <span>✍️</span> <span>Editorial</span>
                </a>
                <a href="{{ route('user-manual.show', 'reviewer') }}" class="rounded-xl px-3.5 py-2 transition flex items-center gap-1.5 {{ $activeRole === 'reviewer' ? 'bg-navy text-white shadow-sm font-black' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                    <span>🔍</span> <span>Reviewer</span>
                </a>
                <a href="{{ route('user-manual.show', 'admin') }}" class="rounded-xl px-3.5 py-2 transition flex items-center gap-1.5 {{ $activeRole === 'admin' ? 'bg-navy text-white shadow-sm font-black' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                    <span>🏛️</span> <span>Conference Admin</span>
                </a>
                @if(auth()->user()->isSuperAdmin())
                    <a href="{{ route('user-manual.show', 'superadmin') }}" class="rounded-xl px-3.5 py-2 transition flex items-center gap-1.5 {{ $activeRole === 'superadmin' ? 'bg-navy text-white shadow-sm font-black' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                        <span>👑</span> <span>Superadmin</span>
                    </a>
                @endif
                <a href="{{ route('user-manual.show', 'viewer') }}" class="rounded-xl px-3.5 py-2 transition flex items-center gap-1.5 {{ $activeRole === 'viewer' ? 'bg-navy text-white shadow-sm font-black' : 'bg-slate-100 text-navy hover:bg-slate-200' }}">
                    <span>👁️</span> <span>Viewer</span>
                </a>
            @else
                <span class="text-muted text-[11px] px-3 py-1 bg-slate-100 rounded-xl font-medium">🔒 Staff User Manuals (Editorial, Reviewer, Admin, Superadmin, Viewer) require authentication.</span>
            @endauth
        </div>
    </div>
</div>
