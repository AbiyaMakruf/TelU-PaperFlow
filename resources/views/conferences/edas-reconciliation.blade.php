<x-layouts.app title="EDAS CSV Reconciliation">
    <div class="mx-auto max-w-[1600px] space-y-6">
        <!-- Header Banner & Workspace Scoping -->
        <div class="card p-6 sm:p-8 bg-gradient-to-r from-navy via-navy-light to-navy text-white shadow-xl">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1.5 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="badge bg-orange text-white text-xs font-black uppercase tracking-wider px-2.5 py-1">Conference Admin Feature</span>
                        @if($activeConference)
                            <span class="badge bg-white/15 text-white text-xs font-bold px-2.5 py-1">📌 {{ $activeConference->name }}</span>
                        @endif
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">EDAS CSV Reconciliation</h1>
                    <p class="text-xs sm:text-sm text-white/80 leading-relaxed max-w-3xl">
                        Unggah file CSV naskah dari EDAS untuk memeriksa secara otomatis paper mana saja yang <strong>Sudah Submit</strong> dan <strong>Belum Submit</strong> di Paperflow.
                    </p>
                </div>

                @if($sessionData)
                    <div class="flex items-center gap-2.5 shrink-0 self-start md:self-auto">
                        <form method="POST" action="{{ route('conferences.edas-reconciliation.reset') }}">
                            @csrf
                            <button type="submit" class="btn border border-white/20 bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-4 py-2.5 transition flex items-center gap-2" title="Clear current session data and upload a new CSV file">
                                <span>🔄 Upload New CSV</span>
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        @if(! $sessionData)
            <!-- Section 1: Upload CSV Area (Shown when no session data exists) -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 card p-6 sm:p-8 space-y-6">
                    <div class="border-b border-navy/10 pb-4">
                        <h2 class="text-lg font-black text-navy flex items-center gap-2">
                            <span>📄 Upload EDAS CSV File</span>
                        </h2>
                        <p class="text-xs text-muted mt-0.5">Pilih atau seret file CSV daftar naskah yang diekspor dari EDAS.</p>
                    </div>

                    <form method="POST" action="{{ route('conferences.edas-reconciliation.upload') }}" enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        <div class="border-2 border-dashed border-navy/20 hover:border-orange rounded-2xl p-6 sm:p-10 text-center bg-slate-50/50 hover:bg-orange/5 transition group cursor-pointer relative">
                            <input type="file" name="csv_file" accept=".csv,.txt,.tsv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required onchange="document.getElementById('file-chosen-name').textContent = this.files[0] ? '📎 Selected File: ' + this.files[0].name : ''">
                            <div class="size-14 mx-auto rounded-full bg-navy/5 text-navy group-hover:bg-orange/10 group-hover:text-orange flex items-center justify-center transition mb-3">
                                <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <p class="text-sm font-extrabold text-navy group-hover:text-orange transition">Klik atau seret file CSV EDAS di sini</p>
                            <p class="text-xs text-muted mt-1">Mendukung format <code>.csv</code>, <code>.txt</code> (maksimal 10 MB)</p>
                            <p id="file-chosen-name" class="mt-3 text-xs font-bold text-orange"></p>
                        </div>

                        <div class="flex items-center justify-end">
                            <button type="submit" class="btn btn-primary px-6 py-3 text-xs font-extrabold shadow-md flex items-center gap-2">
                                <span>🚀 Upload &amp; Run Cross-Check</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- CSV Format Guide Card -->
                <div class="card p-6 space-y-4 bg-slate-50/70 border border-slate-200">
                    <h3 class="text-sm font-extrabold text-navy flex items-center gap-1.5">
                        <span>💡 Format CSV yang Didukung</span>
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Sistem secara otomatis mendeteksi kolom berdasarkan nama header CSV dari EDAS. Pastikan file CSV Anda memiliki kolom:
                    </p>
                    <ul class="space-y-2 text-xs text-slate-700 font-medium">
                        <li class="flex items-start gap-2">
                            <span class="size-1.5 rounded-full bg-orange mt-1.5 shrink-0"></span>
                            <span><strong>Paper ID:</strong> Header <code>paper_id</code>, <code>Paper ID</code>, <code>ID</code>, atau <code>Paper #</code></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="size-1.5 rounded-full bg-orange mt-1.5 shrink-0"></span>
                            <span><strong>Email Author:</strong> Header <code>email</code>, <code>Author Email</code>, <code>contact_email</code>, atau <code>Contact Email</code></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="size-1.5 rounded-full bg-orange mt-1.5 shrink-0"></span>
                            <span><strong>Judul Paper (Opsional):</strong> Header <code>title</code>, <code>Paper Title</code>, atau <code>Name</code></span>
                        </li>
                    </ul>
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-[11px] text-amber-900 leading-normal">
                        <strong>Catatan:</strong> Jika CSV tidak memiliki header, sistem akan otomatis membaca kolom pertama sebagai Paper ID dan kolom kedua sebagai Email Author.
                    </div>
                </div>
            </div>
        @else
            <!-- Section 2: Reconciliation Dashboard Results (Shown when session data exists) -->
            <div x-data="{
                activeTab: 'all',
                searchQuery: '',
                copyEmails() {
                    const missingEmails = {{ Js::from(collect($sessionData['items'])->where('status_state', 'missing')->pluck('edas_email')->filter(fn($e)=>$e !== '-')->unique()->values()) }};
                    if (missingEmails.length === 0) {
                        alert('Tidak ada email pada daftar Belum Submit.');
                        return;
                    }
                    navigator.clipboard.writeText(missingEmails.join(', '));
                    alert('✓ ' + missingEmails.length + ' email penulis yang Belum Submit berhasil disalin ke clipboard!');
                }
            }" class="space-y-6">

                <!-- Stat Cards Grid -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="card p-5 bg-white space-y-2 border-l-4 border-l-navy">
                        <p class="text-xs font-bold text-muted uppercase tracking-wider">Total Paper di EDAS</p>
                        <p class="text-2xl sm:text-3xl font-black text-navy">{{ number_format($sessionData['total_edas_count']) }}</p>
                        <p class="text-[11px] text-muted truncate">File: {{ $sessionData['filename'] }}</p>
                    </div>

                    <div class="card p-5 bg-white space-y-2 border-l-4 border-l-emerald-500">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-muted uppercase tracking-wider">Sudah Submit di Paperflow</p>
                            <span class="badge badge-success text-[10px] font-bold">✓ {{ $sessionData['submission_rate_percent'] }}%</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-emerald-700">{{ number_format($sessionData['submitted_count']) }}</p>
                        <p class="text-[11px] text-emerald-800 font-semibold">Tercatat aktif di Paperflow</p>
                    </div>

                    <div class="card p-5 bg-white space-y-2 border-l-4 border-l-rose-500">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold text-muted uppercase tracking-wider">Belum Submit di Paperflow</p>
                            <span class="badge badge-danger text-[10px] font-bold">✕ {{ number_format(100 - $sessionData['submission_rate_percent'], 1) }}%</span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-rose-700">{{ number_format($sessionData['missing_count']) }}</p>
                        <p class="text-[11px] text-rose-800 font-semibold">Perlu diingatkan / tindak lanjut</p>
                    </div>

                    <div class="card p-5 bg-white space-y-2 border-l-4 border-l-orange">
                        <p class="text-xs font-bold text-muted uppercase tracking-wider">Tingkat Submission</p>
                        <div class="flex items-baseline justify-between">
                            <p class="text-2xl sm:text-3xl font-black text-navy">{{ $sessionData['submission_rate_percent'] }}%</p>
                            <span class="text-xs text-muted font-bold">{{ $sessionData['submitted_count'] }}/{{ $sessionData['total_edas_count'] }}</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 h-2 rounded-full" style="width: {{ $sessionData['submission_rate_percent'] }}%"></div>
                        </div>
                    </div>
                </div>

                <!-- Controls & Filter Toolbar -->
                <div class="card p-4 sm:p-6 space-y-4">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <!-- Filter Tabs -->
                        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-slate-100/80 rounded-xl shrink-0">
                            <button type="button" @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-white text-navy font-black shadow-xs' : 'text-slate-600 hover:text-navy font-bold'" class="px-3.5 py-2 text-xs rounded-lg transition">
                                Semua EDAS ({{ $sessionData['total_edas_count'] }})
                            </button>
                            <button type="button" @click="activeTab = 'missing'" :class="activeTab === 'missing' ? 'bg-rose-600 text-white font-black shadow-xs' : 'text-slate-600 hover:text-navy font-bold'" class="px-3.5 py-2 text-xs rounded-lg transition">
                                Belum Submit ({{ $sessionData['missing_count'] }})
                            </button>
                            <button type="button" @click="activeTab = 'submitted'" :class="activeTab === 'submitted' ? 'bg-emerald-600 text-white font-black shadow-xs' : 'text-slate-600 hover:text-navy font-bold'" class="px-3.5 py-2 text-xs rounded-lg transition">
                                Sudah Submit ({{ $sessionData['submitted_count'] }})
                            </button>
                            @if($sessionData['paperflow_only_count'] > 0)
                                <button type="button" @click="activeTab = 'paperflow_only'" :class="activeTab === 'paperflow_only' ? 'bg-orange text-white font-black shadow-xs' : 'text-slate-600 hover:text-navy font-bold'" class="px-3.5 py-2 text-xs rounded-lg transition">
                                    Hanya di Paperflow ({{ $sessionData['paperflow_only_count'] }})
                                </button>
                            @endif
                        </div>

                        <!-- Actions & Search Box -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <input type="text" x-model="searchQuery" placeholder="Cari ID, Email, atau Judul..." class="form-input text-xs py-2 px-3 min-w-[220px]">
                            
                            <a href="{{ route('conferences.edas-reconciliation.export-missing') }}" class="btn btn-secondary text-xs font-bold py-2 px-3 flex items-center gap-1.5" title="Download file CSV naskah EDAS yang belum submit di Paperflow">
                                📥 Export CSV Belum Submit
                            </a>
                            
                            <button type="button" @click="copyEmails()" class="btn bg-navy hover:bg-navy-light text-white text-xs font-bold py-2 px-3 flex items-center gap-1.5" title="Salin semua email penulis yang belum submit ke clipboard">
                                📋 Copy Email Belum Submit
                            </button>
                        </div>
                    </div>

                    <!-- Main Data Table for EDAS Items -->
                    <div x-show="activeTab !== 'paperflow_only'" class="overflow-x-auto min-w-0">
                        <table class="data-table min-w-[700px]">
                            <thead>
                                <tr>
                                    <th class="w-12 text-center">#</th>
                                    <th class="w-32">EDAS Paper ID</th>
                                    <th>EDAS Author Email</th>
                                    <th>Status di Paperflow</th>
                                    <th>Matching Paperflow Submission</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sessionData['items'] as $item)
                                    @php
                                        $rowSearch = strtolower($item['edas_paper_id'] . ' ' . $item['edas_email'] . ' ' . $item['edas_title'] . ' ' . ($item['paperflow_submission']['title'] ?? ''));
                                    @endphp
                                    <tr x-show="(activeTab === 'all' || (activeTab === 'missing' && '{{ $item['status_state'] }}' === 'missing') || (activeTab === 'submitted' && '{{ $item['status_state'] }}' === 'submitted')) && (!searchQuery || {{ Js::from($rowSearch) }}.includes(searchQuery.toLowerCase()))" class="hover:bg-slate-50/80 transition">
                                        <td class="text-center text-xs font-bold text-muted">{{ $item['row_number'] }}</td>
                                        <td class="font-mono font-bold text-xs whitespace-nowrap text-navy">
                                            {{ $item['edas_paper_id'] }}
                                        </td>
                                        <td class="text-xs">
                                            <p class="font-semibold text-slate-800 break-all">{{ $item['edas_email'] }}</p>
                                            @if($item['edas_title'] !== '-')
                                                <p class="text-[11px] text-muted truncate max-w-xs mt-0.5" title="{{ $item['edas_title'] }}">{{ $item['edas_title'] }}</p>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap">
                                            @if($item['status_state'] === 'submitted')
                                                <span class="badge badge-success text-[10px] font-extrabold inline-flex items-center gap-1">
                                                    <span>✓ Sudah Submit</span>
                                                </span>
                                            @else
                                                <span class="badge badge-danger text-[10px] font-extrabold inline-flex items-center gap-1">
                                                    <span>✕ Belum Submit</span>
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
                                                <span class="text-xs text-muted italic">Tidak ditemukan di Paperflow</span>
                                            @endif
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            @if($item['paperflow_submission'])
                                                <a href="{{ route('submissions.show', $item['paperflow_submission']['id']) }}" class="btn btn-secondary text-xs py-1 px-2.5 font-bold" target="_blank">
                                                    Buka Paper ↗
                                                </a>
                                            @else
                                                <a href="mailto:{{ $item['edas_email'] }}?subject=Reminder: Submission for {{ urlencode($activeConference?->name ?? 'Conference') }} (Paper ID: {{ urlencode($item['edas_paper_id']) }})" class="btn bg-orange hover:bg-orange-dark text-white text-xs py-1 px-2.5 font-bold transition">
                                                    📧 Remind Author
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paperflow Only Table (Submissions in Paperflow not in EDAS CSV) -->
                    @if($sessionData['paperflow_only_count'] > 0)
                        <div x-show="activeTab === 'paperflow_only'" class="overflow-x-auto min-w-0">
                            <div class="p-3 mb-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 font-medium">
                                <strong>Informasi:</strong> Berikut adalah {{ $sessionData['paperflow_only_count'] }} paper yang tercatat di Paperflow tetapi <strong>tidak ditemukan</strong> pada file CSV EDAS yang diunggah.
                            </div>
                            <table class="data-table min-w-[700px]">
                                <thead>
                                    <tr>
                                        <th>Paper ID / Code</th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Email</th>
                                        <th>Status di Paperflow</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sessionData['paperflow_only_items'] as $pfItem)
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="font-mono font-bold text-xs text-navy whitespace-nowrap">
                                                {{ $pfItem['paper_code'] }}
                                                @if($pfItem['paper_id'])
                                                    <span class="text-muted font-normal">({{ $pfItem['paper_id'] }})</span>
                                                @endif
                                            </td>
                                            <td class="font-bold text-navy text-xs break-words max-w-xs">{{ $pfItem['title'] }}</td>
                                            <td class="text-xs font-semibold">{{ $pfItem['author_name'] }}</td>
                                            <td class="text-xs text-muted break-all">{{ $pfItem['author_email'] }}</td>
                                            <td>
                                                <span class="badge badge-{{ $pfItem['status_color'] }} text-[10px]">
                                                    {{ $pfItem['status_label'] }}
                                                </span>
                                            </td>
                                            <td class="text-right whitespace-nowrap">
                                                <a href="{{ route('submissions.show', $pfItem['id']) }}" class="btn btn-secondary text-xs py-1 px-2.5 font-bold" target="_blank">
                                                    Buka Paper ↗
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
