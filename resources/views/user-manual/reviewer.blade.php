<x-layouts.app title="User Manual Reviewer - Paperflow" heading="Panduan Reviewer">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'reviewer'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-amber-500">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-warning text-xs font-black mb-2">Role Staf Reviewer</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Reviewer (Reviewer PIC / EDAS Officer)</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Panduan inspeksi kelayakan naskah, verifikasi IEEE PDF eXpress, pencatatan error EDAS dengan preset tombol cepat, dan approval akhir EDAS.</p>
            </div>

            <!-- Flowchart Overview -->
            <div class="rounded-2xl bg-warm/80 p-4 sm:p-5 border border-navy/10 text-xs sm:text-sm">
                <h3 class="font-extrabold text-navy mb-2 text-sm">🔄 Alur Kerja Utama Reviewer PIC:</h3>
                <ol class="list-decimal pl-5 space-y-1.5 text-slate-800">
                    <li>Buka menu <strong>Paper (`/papers`)</strong> &rarr; Pilih paper dengan status <em>Reviewer Review</em>.</li>
                    <li>Inspeksi kelayakan naskah dan hasil pemeriksaan 16 checklist IEEE dari Editor.</li>
                    <li>Periksa file naskah pada sistem <strong>IEEE PDF eXpress</strong> dan perbarui statusnya (`Pending`, `Passed`, `Failed`).</li>
                    <li>Jika ada kendala EDAS: Gunakan <strong>Preset Tombol Error EDAS</strong> atau tulis catatan error EDAS.</li>
                    <li>Jika butuh perbaikan: Klik <strong>Kembalikan ke Editorial</strong>.</li>
                    <li>Jika paper sempurna: Klik <strong>Setujui &amp; Ready for EDAS</strong>.</li>
                    <li>Setelah Editor mengunggah ke EDAS: Verifikasi referensi EDAS, lalu klik <strong>Approve EDAS &amp; Selesai (Done)</strong>.</li>
                </ol>
            </div>

            <!-- Fitur 1: IEEE PDF eXpress -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>Pengelolaan Status IEEE PDF eXpress</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Reviewer bertanggung jawab melakukan verifikasi sertifikasi PDF eXpress resmi dari IEEE.
                </p>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">Pilihan Status PDF eXpress:</strong>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>🟡 <strong>Pending:</strong> Naskah masih dalam antrean verifikasi atau belum diperiksa.</li>
                        <li>🟢 <strong>✓ Passed / Done:</strong> Naskah telah lolos verifikasi kompatibilitas IEEE PDF eXpress.</li>
                        <li>🔴 <strong>✕ Failed / Error:</strong> Naskah gagal terverifikasi PDF eXpress (font tidak ter-embed, margin salah, dll.).</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 2: EDAS Error Notes & Presets -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Catatan Error EDAS &amp; Preset Tombol Cepat</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Untuk mempercepat pencatatan kendala unggah ke sistem EDAS, Reviewer dapat menggunakan tombol preset kesalahan standar:
                </p>
                <div class="bg-rose-50/50 p-4 rounded-xl border border-rose-200 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">Tombol Preset Error EDAS:</strong>
                    <ul class="list-disc pl-5 space-y-1.5">
                        <li><code>+ Page Size US Letter</code>: Menambahkan pesan error ukuran kertas bukan A4.</li>
                        <li><code>+ Min 5 Pages</code>: Menambahkan pesan error naskah kurang dari 5 halaman penuh.</li>
                        <li><code>+ Doubleblind Author Visible</code>: Menambahkan pesan error identitas author masih terlihat pada konferensi doubleblind.</li>
                        <li><code>+ IEEE Copyright Missing</code>: Menambahkan pesan error form hak cipta IEEE belum diisi oleh author.</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 3: Aksi Tahap Reviewer & Final EDAS Approval -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Persetujuan &amp; Approval EDAS Final</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Setujui &amp; Ready for EDAS:</strong> Memindahkan status paper ke <em>Ready for EDAS</em> agar Editor dapat melakukan proses upload akhir ke EDAS.</li>
                        <li><strong>Approve EDAS &amp; Selesai:</strong> Setelah Editor mencatat referensi EDAS ID, Reviewer memeriksa keabsahan upload tersebut. Klik <strong>Approve EDAS &amp; Selesai</strong> untuk menyelesaikan alur paper hingga status **Done**.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
