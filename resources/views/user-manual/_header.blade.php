@props(['activeRole' => 'author'])

<div class="space-y-6">
    <!-- Header Title -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-navy/10 pb-5">
        <div>
            <p class="eyebrow">Pusat Dokumentasi &amp; Panduan Pengguna</p>
            <h1 class="page-title leading-tight">User Manual Paperflow</h1>
            <p class="page-subtitle max-w-3xl mt-1">Panduan lengkap langkah-demi-langkah, alur kerja, dan fitur yang tersedia untuk setiap role dalam ekosistem Paperflow.</p>
        </div>
        <div class="shrink-0">
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-secondary text-xs font-extrabold">&larr; Kembali ke Dashboard</a>
            @else
                <a href="{{ route('home') }}" class="btn btn-secondary text-xs font-extrabold">&larr; Kembali ke Beranda</a>
            @endauth
        </div>
    </div>

    <!-- Role Ecosystem Matrix Overview Card -->
    <details class="card overflow-hidden border-2 border-orange/30 bg-amber-50/20 max-w-full" open>
        <summary class="cursor-pointer p-4 sm:p-5 font-black text-navy text-sm sm:text-base flex items-center justify-between select-none">
            <span class="flex items-center gap-2">
                <span>🗺️</span> <span>Matriks Role &amp; Pembagian Tanggung Jawab Ekosistem Paperflow</span>
            </span>
            <span class="text-orange font-bold text-lg">+</span>
        </summary>
        <div class="border-t border-navy/10 p-4 sm:p-6 bg-white space-y-4 text-xs sm:text-sm text-slate-800">
            <p class="leading-relaxed text-muted">
                Paperflow membagi wewenang ke dalam <strong>6 role utama</strong> untuk menjamin akuntabilitas dan alur kerja editorial yang terstruktur. Meskipun Anda memiliki satu role (misalnya Editor), Anda dapat meninjau matriks di bawah untuk memahami peran Admin Conference dan Reviewer:
            </p>
            <div class="overflow-x-auto min-w-0 max-w-full">
                <table class="data-table min-w-[640px] text-xs">
                    <thead>
                        <tr>
                            <th class="w-36">Role</th>
                            <th class="w-32">Akses Utama</th>
                            <th>Fungsi Utama &amp; Tanggung Jawab dalam Sistem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="{{ $activeRole === 'author' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">👤 Author (Penulis)</td>
                            <td><span class="badge badge-success text-[10px]">Publik (Bebas)</span></td>
                            <td class="leading-relaxed">Mengunggah naskah paper (`.docx`/`.zip`), memantau *Live Checklist* pemeriksaan di portal token (`/submission/access/{token}`), memperbarui detail paper, serta mengunggah file revisi dan PDF petunjuk revisi.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'editorial' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">✍️ Editorial (Editor PIC)</td>
                            <td><span class="badge badge-primary text-[10px]">Staf Terotentikasi</span></td>
                            <td class="leading-relaxed">Memeriksa kelayakan format 16 item standar IEEE, menyusun template feedback revisi otomatis (`⚡ Unchecked Items`), berkomunikasi via Email &amp; WhatsApp, mengunggah versi file baru, serta meneruskan paper ke Reviewer.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'reviewer' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">🔍 Reviewer (Reviewer PIC)</td>
                            <td><span class="badge badge-primary text-[10px]">Staf Terotentikasi</span></td>
                            <td class="leading-relaxed">Memeriksa hasil kerja Editor, memverifikasi status IEEE PDF eXpress (`Passed`/`Failed`), mengelola catatan error EDAS dengan preset tombol cepat, mengembalikan ke editorial, serta menyetujui hasil upload EDAS.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'admin' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">🏛️ Conference Admin</td>
                            <td><span class="badge badge-warning text-[10px]">Staf Terotentikasi</span></td>
                            <td class="leading-relaxed">Mengonfigurasi conference, membangun form submission custom, mengedit template email &amp; CC default, memilih storage (Supabase / Google Drive OAuth), mengelola anggota tim, memvalidasi paper awal, serta export CSV.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'superadmin' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">👑 Superadmin</td>
                            <td><span class="badge badge-danger text-[10px]">Akses Penuh Sistem</span></td>
                            <td class="leading-relaxed">Membuat pengguna baru, membuat conference baru, memantau kesehatan database &amp; storage sistem (`/monitoring`), mengecek audit log aktivitas seluruh staf, serta melakukan impersonasi akun pengguna.</td>
                        </tr>
                        <tr class="{{ $activeRole === 'viewer' ? 'bg-orange/10 font-bold' : '' }}">
                            <td class="font-extrabold text-navy">👁️ Viewer (Pengamat)</td>
                            <td><span class="badge badge-neutral text-[10px]">Staf Read-Only</span></td>
                            <td class="leading-relaxed">Akses baca-saja (*read-only*) untuk meninjau progres conference, daftar paper, dan matriks beban kerja staf PIC tanpa hak mengubah data.</td>
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
                <span>👤</span> <span>Author (Publik)</span>
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
                <span class="text-muted text-[11px] px-3 py-1 bg-slate-100 rounded-xl font-medium">🔒 Manual Staf (Editorial, Reviewer, Admin, Superadmin, Viewer) memerlukan login.</span>
            @endauth
        </div>
    </div>
</div>
