<x-layouts.app title="User Manual Viewer - Paperflow" heading="Panduan Viewer">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'viewer'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-slate-600">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-neutral text-xs font-black mb-2">Role Staf Viewer (Read-Only)</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Viewer (Pengamat Conference)</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Panduan peninjauan telemetri progres conference, statistik submission, daftar paper read-only, dan matriks beban kerja staf PIC.</p>
            </div>

            <!-- Fitur 1: Telemetri Dashboard -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>Monitoring Telemetri Dashboard (`/dashboard`)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Viewer dapat memantau pergerakan data naskah secara real-time pada dashboard conference yang aktif:
                </p>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Kartu Statistik Utama:</strong> Menampilkan jumlah Total Paper, Submission Baru, Dalam Review Editorial, Dalam Review Reviewer, Ready for EDAS, dan Selesai.</li>
                        <li><strong>Grafik &amp; Distribusi Format:</strong> Meninjau persentase naskah format Word (DOCX) vs LaTeX (ZIP).</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 2: Workload Summary Matrix -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Matriks Beban Kerja Staf PIC (PIC Workload Matrix)</span>
                </h3>
                <div class="bg-amber-50/50 p-4 rounded-xl border border-orange/20 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Daftar Staf PIC:</strong> Menampilkan tabel jumlah naskah aktif yang sedang ditangani oleh masing-masing Editor dan Reviewer.</li>
                        <li><strong>Modal Kontak Staf:</strong> Mengeklik nama staf untuk membuka modal informasi kontak (Email, WhatsApp, Afiliasi, dan Role Conference).</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 3: Daftar Paper Read-Only -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Pencarian &amp; Filter Read-Only (`/papers`)</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Pencarian Paper:</strong> Mencari naskah berdasarkan Paper ID, Judul, atau Nama Corresponding Author.</li>
                        <li><strong>Read-Only Access:</strong> Viewer dapat melihat status dan detail paper tanpa memiliki tombol aksi pengeditan data atau pengubahan status.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
