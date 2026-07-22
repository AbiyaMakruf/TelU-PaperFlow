<x-layouts.app title="User Manual Author - Paperflow" heading="Panduan Author">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'author'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-orange">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-success text-xs font-black mb-2">Akses Publik</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Author (Penulis / Pengunggah Paper)</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Panduan langkah demi langkah untuk pengajuan naskah ilmiah, pemantauan *Live Checklist*, dan pengunggahan revisi.</p>
            </div>

            <!-- Flowchart Overview -->
            <div class="rounded-2xl bg-warm/80 p-4 sm:p-5 border border-navy/10 text-xs sm:text-sm">
                <h3 class="font-extrabold text-navy mb-2 text-sm">🔄 Alur Kerja Utama Author:</h3>
                <ol class="list-decimal pl-5 space-y-1.5 text-slate-800">
                    <li>Membuka Landing Page Conference &rarr; Mengeklik <strong>Submit Paper</strong>.</li>
                    <li>Mengisi form pendaftaran &amp; mengunggah file naskah editable (`.docx` atau `.zip`).</li>
                    <li>Menerima link portal token rahasia via Email &amp; WhatsApp.</li>
                    <li>Membuka Portal Author (`/submission/access/{token}`) untuk memantau status secara real-time.</li>
                    <li>Jika Editor meminta revisi: Melihat <em>Live Checklist Results</em>, mengunggah naskah revisi baru, dan mengunggah PDF Petunjuk Revisi.</li>
                </ol>
            </div>

            <!-- Detail Langkah 1 -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>Pengajuan Paper Baru (Public Submission Form)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Setiap conference memiliki URL pengajuan publik sendiri dengan format: <code>/{conference-slug}/submit</code>.
                </p>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">Langkah Pengisian Form:</strong>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Paper ID Conference:</strong> Masukkan nomor Paper ID resmi dari sistem konferensi (misal EDAS ID atau kode registrasi).</li>
                        <li><strong>Data Corresponding Author:</strong> Masukkan Nama Lengkap, Alamat Email Aktif, dan Nomor Telepon/WhatsApp dengan pemilih Kode Negara internasional.</li>
                        <li><strong>Data Co-Authors (Opsional):</strong> Klik <em>+ Tambah Co-Author</em> untuk memasukkan Nama, Email, dan Afiliasi penulis pendamping.</li>
                        <li><strong>Upload File Naskah Editable:</strong> Pilih naskah format Microsoft Word (<code>.docx</code>) atau paket LaTeX (<code>.zip</code>). Ukuran file maksimum sesuai batas conference (default 10 MB).</li>
                        <li><strong>Verifikasi Captcha:</strong> Selesaikan verifikasi Cloudflare Turnstile jika diaktifkan oleh admin.</li>
                    </ul>
                </div>
            </div>

            <!-- Detail Langkah 2 -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Akses Portal Rahasia Author (Token Portal)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Setelah berhasil submit, Anda akan mendapatkan <strong>Token Akses Portal Rahasia</strong> yang dikirim ke email Anda. Tautan portal memiliki format: <code>/submission/access/{token}</code>.
                </p>
                <div class="bg-amber-50/50 p-4 rounded-xl border border-orange/20 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">💡 Fitur di Portal Author:</strong>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Monitoring Timeline Status:</strong> Memantau tahapan paper (Submitted &rarr; Validated &rarr; Editorial Review &rarr; Reviewer Review &rarr; Ready for EDAS &rarr; Done).</li>
                        <li><strong>Pembaruan Data Submission:</strong> Mengedit kembali nama author, email, telepon, dan co-authors jika terjadi kesalahan ketik.</li>
                        <li><strong>Unduh Versi File:</strong> Mengunduh kembali versi file yang pernah diunggah sebelumnya.</li>
                    </ul>
                </div>
            </div>

            <!-- Detail Langkah 3 -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Pemantauan Live Checklist &amp; Unggah Revisi</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Jika Tim Editorial meminta perbaikan, Anda akan menerima email/WhatsApp notifikasi. Saat portal dibuka, kartu <strong>Live Checklist Hasil Pemeriksaan IEEE</strong> akan muncul secara otomatis.
                </p>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">Langkah Mengirim Revisi:</strong>
                    <ol class="list-decimal pl-5 space-y-1">
                        <li>Pelajari poin-poin checklist yang ditandai merah/belum valid oleh Editor.</li>
                        <li>Buka file naskah di komputer Anda, lakukan perbaikan sesuai petunjuk Editor.</li>
                        <li>Buka bagian <strong>Unggah Revisi Naskah</strong> di Portal Author.</li>
                        <li>Pilih file naskah editable revisi baru (<code>.docx</code> / <code>.zip</code>).</li>
                        <li><em>(Opsional tapi Disarankan)</em> Unggah file <strong>PDF Petunjuk / Tanggapan Revisi</strong> yang menjelaskan poin-poin perbaikan yang telah Anda lakukan.</li>
                        <li>Klik <strong>Kirim Naskah Revisi</strong>. Status paper akan otomatis kembali ke Tim Editorial untuk diperiksa ulang.</li>
                    </ol>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
