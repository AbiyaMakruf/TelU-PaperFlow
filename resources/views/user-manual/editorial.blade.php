<x-layouts.app title="User Manual Editorial - Paperflow" heading="Panduan Editorial">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'editorial'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-navy">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-primary text-xs font-black mb-2">Role Staf Editorial</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Editorial (Editor PIC)</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Panduan lengkap untuk pemeriksaan kelayakan format naskah 16 poin IEEE, komunikasi author, pembuat template revisi otomatis, dan pengunggah versi naskah.</p>
            </div>

            <!-- Flowchart Overview -->
            <div class="rounded-2xl bg-warm/80 p-4 sm:p-5 border border-navy/10 text-xs sm:text-sm">
                <h3 class="font-extrabold text-navy mb-2 text-sm">🔄 Alur Kerja Utama Editor PIC:</h3>
                <ol class="list-decimal pl-5 space-y-1.5 text-slate-800">
                    <li>Buka menu <strong>Paper (`/papers`)</strong> &rarr; Pilih paper yang ditugaskan kepada Anda.</li>
                    <li>Periksa kelayakan format naskah menggunakan <strong>16 Item IEEE Compliance Checklist</strong>.</li>
                    <li>Jika ada kesalahan format: Klik <code>⚡ Gunakan Template Revisi (Unchecked Items)</code> untuk menyusun draf pesan otomatis.</li>
                    <li>Kirim pesan perbaikan ke author via <strong>Email CC Chips</strong> atau <strong>WhatsApp Direct Action</strong> (`wa.me`).</li>
                    <li>Ubah status paper ke <strong>Minta Revisi Author</strong>.</li>
                    <li>Setelah author mengunggah revisi: Periksa ulang checklist, tandai 100% valid, lalu klik <strong>Kirim ke Reviewer</strong>.</li>
                </ol>
            </div>

            <!-- Fitur 1: Checklist 16 IEEE -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>Pemeriksaan Checklist 16 IEEE Compliance</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Setiap paper dilengkapi dengan 16 item standar IEEE formatting. Setiap item memiliki <strong>Guidance Accordion</strong> yang menjelaskan secara detail aturan dan standar pemeriksaan.
                </p>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">Langkah-Langkah:</strong>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Buka kartu <strong>Checklist Editorial</strong> di halaman detail paper.</li>
                        <li>Klik <code>Guidance Accordion +</code> untuk membaca instruksi resmi pemeriksaan per item.</li>
                        <li>Beri tanda centang pada item yang telah memenuhi syarat, dan tulis catatan khusus pada kolom textarea jika ada catatan.</li>
                        <li>Klik <strong>Simpan Checklist</strong> untuk menyimpan progres.</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 2: Auto-Generate Template Revisi -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Otomatisasi Draf Revisi (`⚡ Unchecked Items`)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Editor tidak perlu mengetik ulang daftar perbaikan secara manual!
                </p>
                <div class="bg-amber-50/50 p-4 rounded-xl border border-orange/20 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">Cara Menggunakan:</strong>
                    <ol class="list-decimal pl-5 space-y-1">
                        <li>Biarkan item yang masih salah/belum sesuai dalam kondisi <em>unchecked</em> (tidak dicentang).</li>
                        <li>Klik tombol <code>⚡ Gunakan Template Revisi (Unchecked Items)</code> di bagian bawah form checklist.</li>
                        <li>Sistem akan otomatis mengekstrak seluruh judul item dan instruksi guidance dari item yang belum dicentang, lalu menyusunnya ke dalam kotak <strong>Komunikasi Author</strong> secara rapi.</li>
                    </ol>
                </div>
            </div>

            <!-- Fitur 3: Komunikasi Author (CC Email & WhatsApp) -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Komunikasi Author (Email Chips &amp; WhatsApp Direct)</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1.5">
                        <li><strong>Dua Jenis Catatan:</strong> 
                            <br>• 🔒 <em>Catatan Internal:</em> Rahasia khusus tim editorial/reviewer (tidak pernah terkirim ke author).
                            <br>• 📩 <em>Feedback Author:</em> Dilihat oleh author di portal token dan dapat dikirim via Email/WhatsApp.
                        </li>
                        <li><strong>Interactive CC Email Chips:</strong> Ketik email CC tambahan lalu tekan koma/Enter. Email CC default dari conference akan otomatis terisi dan dapat dihapus per tag.</li>
                        <li><strong>WhatsApp Direct Action (`wa.me`):</strong> Mengeklik tombol <code>📱 Kirim lewat WhatsApp ↗</code> akan otomatis membuka aplikasi WhatsApp dengan pesan draf feedback yang sudah terisi lengkap.</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 4: Versioning File & Aksi Tahap -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>4.</span> <span>Versioning File &amp; Penerusan ke Reviewer</span>
                </h3>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Versioning File:</strong> Editor dapat mengunggah versi naskah perbaikan baru, mengunduh file lama, melakukan inline preview text/PDF, dan menandai versi final (`🏁 Final`).</li>
                        <li><strong>Kirim ke Reviewer:</strong> Setelah seluruh 16 item checklist dicentang valid, opsi tombol <strong>Kirim ke Reviewer</strong> akan aktif untuk melimpahkan paper ke Reviewer PIC.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
