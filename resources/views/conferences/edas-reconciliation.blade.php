<x-layouts.app :title="'EDAS CSV Reconciliation · '.$activeConference->name">
    <div class="w-full space-y-6">
        <x-conference-header :conference="$activeConference" active="edas" />

        <!-- Header Card -->
        <div class="card p-6 bg-white border border-slate-200 text-navy shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1.5 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="badge bg-orange text-white text-xs font-black uppercase tracking-wider px-2.5 py-1">Conference Workflow</span>
                        <span class="badge bg-navy/10 text-navy text-xs font-bold px-2.5 py-1">📌 {{ $activeConference->name }}</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-navy">EDAS CSV Reconciliation</h1>
                    <p class="text-xs text-slate-600 leading-relaxed max-w-3xl">
                        Cross-check manuscript records from EDAS against live Paperflow submissions for <strong>{{ $activeConference->name }}</strong>. Data is persisted securely in the database and visible to all conference staff.
                    </p>
                </div>

                @if($reconciledData)
                    <div class="flex flex-wrap items-center gap-2.5 shrink-0 self-start md:self-auto">
                        <form method="POST" action="{{ route('conferences.edas-reconciliation.refresh', $activeConference) }}">
                            @csrf
                            <button type="submit" class="btn border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 text-xs font-black px-4 py-2.5 transition flex items-center gap-2 shadow-2xs" title="Re-sync reconciliation table with latest database submissions">
                                <span>🔄 Refresh Data</span>
                            </button>
                        </form>

                        @can('update', $activeConference)
                            <form method="POST" action="{{ route('conferences.edas-reconciliation.reset', $activeConference) }}" onsubmit="return confirm('Are you sure you want to clear the current EDAS reconciliation data from the database?');">
                                @csrf
                                <button type="submit" class="btn border border-navy/20 bg-navy/5 hover:bg-navy/10 text-navy text-xs font-bold px-4 py-2.5 transition flex items-center gap-2" title="Clear current reconciliation data and upload a new CSV file">
                                    <span>🗑️ Reset Data</span>
                                </button>
                            </form>
                        @endcan
                    </div>
                @endif
            </div>
        </div>

        @if(! $reconciledData)
            <!-- Section 1: Upload CSV Area (Shown when no stored reconciliation data exists) -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 card p-6 sm:p-8 space-y-6">
                    <div class="border-b border-navy/10 pb-4">
                        <h2 class="text-lg font-black text-navy flex items-center gap-2">
                            <span>📄 Upload EDAS CSV File</span>
                        </h2>
                        <p class="text-xs text-muted mt-0.5">Select or drag and drop the manuscript list CSV exported from EDAS for {{ $activeConference->name }}.</p>
                    </div>

                    @can('update', $activeConference)
                        <form method="POST" action="{{ route('conferences.edas-reconciliation.upload', $activeConference) }}" enctype="multipart/form-data" class="space-y-5">
                            @csrf
                            <div class="border-2 border-dashed border-navy/20 hover:border-orange rounded-2xl p-6 sm:p-10 text-center bg-slate-50/50 hover:bg-orange/5 transition group cursor-pointer relative">
                                <input type="file" name="csv_file" accept=".csv,.txt,.tsv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required onchange="document.getElementById('file-chosen-name').textContent = this.files[0] ? '📎 Selected File: ' + this.files[0].name : ''">
                                <div class="size-14 mx-auto rounded-full bg-navy/5 text-navy group-hover:bg-orange/10 group-hover:text-orange flex items-center justify-center transition mb-3">
                                    <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <p class="text-sm font-extrabold text-navy group-hover:text-orange transition">Click or drag the EDAS CSV file here</p>
                                <p class="text-xs text-muted mt-1">Supports <code>.csv</code>, <code>.txt</code> formats (up to 10 MB)</p>
                                <p id="file-chosen-name" class="mt-3 text-xs font-bold text-orange"></p>
                            </div>

                            <div class="flex items-center justify-end">
                                <button type="submit" class="btn btn-primary px-6 py-3 text-xs font-extrabold shadow-md flex items-center gap-2">
                                    <span>🚀 Upload &amp; Run Cross-Check</span>
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="p-6 bg-amber-50 border border-amber-200 rounded-2xl text-center space-y-2">
                            <p class="text-sm font-black text-amber-900">🔒 EDAS CSV Not Yet Uploaded</p>
                            <p class="text-xs text-amber-800">
                                Only Superadmin and Conference Admin can upload EDAS CSV files. Once uploaded by an admin, the reconciliation data will automatically appear here for all team members.
                            </p>
                        </div>
                    @endcan
                </div>

                <!-- CSV Format Guide Card -->
                <div class="card p-6 space-y-4 bg-slate-50/70 border border-slate-200">
                    <h3 class="text-sm font-extrabold text-navy flex items-center gap-1.5">
                        <span>💡 Supported CSV Format</span>
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        The system automatically detects columns based on EDAS CSV header names. Paper ID is required:
                    </p>
                    <ul class="space-y-2 text-xs text-slate-700 font-medium">
                        <li class="flex items-start gap-2">
                            <span class="size-1.5 rounded-full bg-orange mt-1.5 shrink-0"></span>
                            <span><strong>Paper ID (Required):</strong> Header <code>paper_id</code>, <code>Paper ID</code>, <code>ID</code>, <code>Paper</code>, or <code>Paper #</code></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="size-1.5 rounded-full bg-orange mt-1.5 shrink-0"></span>
                            <span><strong>Paper Title (Optional):</strong> Header <code>title</code>, <code>Paper Title</code>, or <code>Name</code></span>
                        </li>
                    </ul>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-[11px] text-emerald-900 leading-normal space-y-1">
                        <strong class="block font-bold">✨ Smart Matching &amp; Warnings:</strong>
                        <p class="text-[11px]">
                            The system tolerates common formatting variations (e.g. <code>#12345</code> vs <code>12345</code> or <code>paper-123</code>) and flags warnings if paper ID formats or titles differ noticeably.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <!-- Section 2: Reconciliation Dashboard Results (Shown when stored data exists) -->
            <div x-data="{
                activeTab: 'all',
                searchQuery: ''
            }" class="space-y-6">

                <!-- Stat Cards Grid -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="card p-5 bg-white space-y-2 border-l-4 border-l-navy shadow-xs">
                        <p class="text-xs font-bold text-muted uppercase tracking-wider">Total EDAS Papers</p>
                        <p class="text-2xl sm:text-3xl font-black text-navy">{{ number_format($reconciledData['total_edas_count']) }}</p>
                        <p class="text-[11px] text-muted truncate" title="Uploaded by {{ $reconciledData['uploaded_by_name'] ?? 'Admin' }}">File: {{ $reconciledData['filename'] }}</p>
                    </div>

                    <div class="card p-5 bg-white space-y-2 border-l-4 border-l-emerald-500 shadow-xs">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-muted uppercase tracking-wider">Submitted in Paperflow</p>
                            <span class="badge badge-success text-[10px] font-bold">✓ {{ $reconciledData['submission_rate_percent'] }}%</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-emerald-700">{{ number_format($reconciledData['submitted_count']) }}</p>
                        <p class="text-[11px] text-emerald-800 font-semibold">Matched active in Paperflow</p>
                    </div>

                    <div class="card p-5 bg-white space-y-2 border-l-4 border-l-rose-500 shadow-xs">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-muted uppercase tracking-wider">Missing in Paperflow</p>
                            <span class="badge badge-danger text-[10px] font-bold">✕ {{ number_format(100 - $reconciledData['submission_rate_percent'], 1) }}%</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-rose-700">{{ number_format($reconciledData['missing_count']) }}</p>
                        <p class="text-[11px] text-rose-800 font-semibold">Requires reminder / follow-up</p>
                    </div>

                    <div class="card p-5 bg-white space-y-2 border-l-4 border-l-amber-500 shadow-xs">
                        <p class="text-xs font-bold text-muted uppercase tracking-wider">Format / Title Warnings</p>
                        <div class="flex items-baseline justify-between">
                            <p class="text-2xl sm:text-3xl font-black text-amber-700">{{ number_format($reconciledData['warning_count']) }}</p>
                            <span class="text-xs text-amber-900 font-bold">ID / Title Variations</span>
                        </div>
                        <p class="text-[11px] text-amber-800 font-medium truncate">Tolerated paper ID match</p>
                    </div>
                </div>

                <!-- Controls & Filter Toolbar -->
                <div class="card p-4 sm:p-6 space-y-4 shadow-sm">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <!-- Filter Tabs -->
                        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-slate-100/80 rounded-xl shrink-0">
                            <button type="button" @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-white text-navy font-black shadow-xs' : 'text-slate-600 hover:text-navy font-bold'" class="px-3.5 py-2 text-xs rounded-lg transition">
                                All EDAS ({{ $reconciledData['total_edas_count'] }})
                            </button>
                            <button type="button" @click="activeTab = 'missing'" :class="activeTab === 'missing' ? 'bg-rose-600 text-white font-black shadow-xs' : 'text-slate-600 hover:text-navy font-bold'" class="px-3.5 py-2 text-xs rounded-lg transition">
                                Missing ({{ $reconciledData['missing_count'] }})
                            </button>
                            <button type="button" @click="activeTab = 'submitted'" :class="activeTab === 'submitted' ? 'bg-emerald-600 text-white font-black shadow-xs' : 'text-slate-600 hover:text-navy font-bold'" class="px-3.5 py-2 text-xs rounded-lg transition">
                                Submitted ({{ $reconciledData['submitted_count'] }})
                            </button>
                            @if($reconciledData['warning_count'] > 0)
                                <button type="button" @click="activeTab = 'warnings'" :class="activeTab === 'warnings' ? 'bg-amber-500 text-white font-black shadow-xs' : 'text-slate-600 hover:text-navy font-bold'" class="px-3.5 py-2 text-xs rounded-lg transition">
                                    ⚠️ Warnings ({{ $reconciledData['warning_count'] }})
                                </button>
                            @endif
                            @if($reconciledData['paperflow_only_count'] > 0)
                                <button type="button" @click="activeTab = 'paperflow_only'" :class="activeTab === 'paperflow_only' ? 'bg-orange text-white font-black shadow-xs' : 'text-slate-600 hover:text-navy font-bold'" class="px-3.5 py-2 text-xs rounded-lg transition">
                                    Paperflow Only ({{ $reconciledData['paperflow_only_count'] }})
                                </button>
                            @endif
                        </div>

                        <!-- Actions & Search Box -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <input type="text" x-model="searchQuery" placeholder="Search Paper ID or Title..." class="form-input text-xs py-2 px-3 min-w-[220px]">

                            <form method="POST" action="{{ route('conferences.edas-reconciliation.refresh', $activeConference) }}" class="inline-block">
                                @csrf
                                <button type="submit" class="btn btn-secondary text-xs font-extrabold py-2 px-3 flex items-center gap-1.5 text-navy hover:text-orange" title="Re-sync reconciliation table with latest database submissions">
                                    <span>🔄 Refresh</span>
                                </button>
                            </form>

                            <a href="{{ route('conferences.edas-reconciliation.export-missing', $activeConference) }}" class="btn btn-secondary text-xs font-bold py-2 px-3 flex items-center gap-1.5" title="Download CSV file of EDAS papers not yet submitted in Paperflow">
                                📥 Export Missing CSV
                            </a>
                        </div>
                    </div>

                    <!-- Main Data Table for EDAS Items -->
                    <div x-show="activeTab !== 'paperflow_only'" class="overflow-x-auto min-w-0">
                        <table class="data-table min-w-[750px]">
                            <thead>
                                <tr>
                                    <th class="w-12 text-center">#</th>
                                    <th class="w-36">EDAS Paper ID</th>
                                    <th>EDAS Paper Title</th>
                                    <th>Paperflow Status</th>
                                    <th>Matching Paperflow Submission</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reconciledData['items'] as $item)
                                    @php
                                        $rowSearch = strtolower($item['edas_paper_id'] . ' ' . $item['edas_title'] . ' ' . ($item['paperflow_submission']['title'] ?? '') . ' ' . ($item['warning_message'] ?? ''));
                                        $hasWarning = ! empty($item['warning_message']);
                                    @endphp
                                    <tr x-show="(activeTab === 'all' || (activeTab === 'missing' && '{{ $item['status_state'] }}' === 'missing') || (activeTab === 'submitted' && '{{ $item['status_state'] }}' === 'submitted') || (activeTab === 'warnings' && {{ $hasWarning ? 'true' : 'false' }})) && (!searchQuery || {{ Js::from($rowSearch) }}.includes(searchQuery.toLowerCase()))" class="hover:bg-slate-50/80 transition">
                                        <td class="text-center text-xs font-bold text-muted">{{ $item['row_number'] }}</td>
                                        <td class="font-mono font-bold text-xs whitespace-nowrap text-navy">
                                            {{ $item['edas_paper_id'] }}
                                        </td>
                                        <td class="text-xs">
                                            <p class="font-semibold text-slate-800 break-words">{{ $item['edas_title'] }}</p>
                                            @if(! empty($item['warning_message']))
                                                <div class="mt-1 inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-900 border border-amber-300">
                                                    <span>⚠️</span>
                                                    <span>{{ $item['warning_message'] }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap">
                                            @if($item['status_state'] === 'submitted')
                                                <span class="badge badge-success text-[10px] font-extrabold inline-flex items-center gap-1">
                                                    <span>✓ Submitted</span>
                                                </span>
                                            @else
                                                <span class="badge badge-danger text-[10px] font-extrabold inline-flex items-center gap-1">
                                                    <span>✕ Missing</span>
                                                </span>
                                            @endif
                                        </td>
                                        <td class="min-w-[220px]">
                                            @if($item['paperflow_submission'])
                                                <div class="space-y-1">
                                                    <p class="font-bold text-navy text-xs leading-snug break-words">
                                                        <span class="text-muted font-mono font-normal">[{{ $item['paperflow_submission']['paper_code'] }}]</span>
                                                        {{ $item['paperflow_submission']['title'] }}
                                                    </p>
                                                    <div class="flex items-center gap-2 text-[11px]">
                                                        <span class="badge badge-{{ $item['paperflow_submission']['status_color'] }} text-[10px]">
                                                            {{ $item['paperflow_submission']['status_label'] }}
                                                        </span>
                                                        <span class="text-slate-600 truncate">{{ $item['paperflow_submission']['author_name'] }}</span>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-xs text-muted italic">Not found in Paperflow</span>
                                            @endif
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            @if($item['paperflow_submission'])
                                                <a href="{{ route('submissions.show', $item['paperflow_submission']['id']) }}" class="btn btn-secondary text-xs py-1 px-2.5 font-bold" target="_blank">
                                                    Open Paper ↗
                                                </a>
                                            @else
                                                <span class="text-xs text-rose-600 font-bold">Unsubmitted</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paperflow Only Table (Submissions in Paperflow not in EDAS CSV) -->
                    @if($reconciledData['paperflow_only_count'] > 0)
                        <div x-show="activeTab === 'paperflow_only'" class="overflow-x-auto min-w-0">
                            <div class="p-3 mb-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 font-medium">
                                <strong>Information:</strong> The following {{ $reconciledData['paperflow_only_count'] }} papers are recorded in Paperflow but <strong>were not found</strong> in the uploaded EDAS CSV file.
                            </div>
                            <table class="data-table min-w-[650px]">
                                <thead>
                                    <tr>
                                        <th>Paper Code</th>
                                        <th>Paper Title</th>
                                        <th>Corresponding Author</th>
                                        <th>Paperflow Status</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($reconciledData['paperflow_only_items'] as $pfItem)
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="font-mono font-bold text-xs text-navy whitespace-nowrap">{{ $pfItem['paper_code'] }}</td>
                                            <td class="text-xs font-semibold text-navy">{{ $pfItem['title'] }}</td>
                                            <td class="text-xs text-slate-700">
                                                <p class="font-bold">{{ $pfItem['author_name'] }}</p>
                                                <p class="text-muted text-[11px]">{{ $pfItem['author_email'] }}</p>
                                            </td>
                                            <td class="whitespace-nowrap">
                                                <span class="badge badge-{{ $pfItem['status_color'] }} text-[10px]">
                                                    {{ $pfItem['status_label'] }}
                                                </span>
                                            </td>
                                            <td class="text-right whitespace-nowrap">
                                                <a href="{{ route('submissions.show', $pfItem['id']) }}" class="btn btn-secondary text-xs py-1 px-2.5 font-bold" target="_blank">
                                                    Open Paper ↗
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
