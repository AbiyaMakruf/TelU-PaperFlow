<x-layouts.public title="Panduan Author - Paperflow">
    <div class="mx-auto max-w-5xl space-y-8">
        <!-- Navigation Header for Author Manual Page -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-navy/10 pb-5">
            <div>
                <p class="eyebrow !text-orange">Pusat Dokumentasi Publik Author</p>
                <h1 class="text-3xl sm:text-4xl font-black text-navy leading-tight">Panduan Penulis (Author User Manual)</h1>
                <p class="text-sm text-muted mt-1">Petunjuk resmi pengajuan naskah ilmiah, pemantauan *Live Checklist*, dan pengunggahan revisi pada sistem Paperflow.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('home') }}" class="btn btn-secondary text-xs font-extrabold">&larr; Beranda</a>
                @auth
                    <a href="{{ route('user-manual.index') }}" class="btn btn-primary text-xs font-extrabold">Manual Staf Editorial &rarr;</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary text-xs font-extrabold">Login Staf &rarr;</a>
                @endauth
            </div>
        </div>

        <!-- Role Ecosystem Explanation Matrix for Authors -->
        <details class="card overflow-hidden border-2 border-orange/30 bg-amber-50/20 max-w-full" open>
            <summary class="cursor-pointer p-4 sm:p-5 font-black text-navy text-sm sm:text-base flex items-center justify-between select-none">
                <span class="flex items-center gap-2">
                    <span>🗺️</span> <span>Bagaimana Paper Anda Diproses dalam Ekosistem Paperflow?</span>
                </span>
                <span class="text-orange font-bold text-lg">+</span>
            </summary>
            <div class="border-t border-navy/10 p-4 sm:p-6 bg-white space-y-4 text-xs sm:text-sm text-slate-800">
                <p class="leading-relaxed text-muted">
                    Paperflow mengelola alur karya ilmiah dari pengajuan awal hingga siap diunggah ke publikasi akhir (seperti EDAS/IEEE). Berikut adalah peranan setiap tim yang menangani paper Anda:
                </p>
                <div class="overflow-x-auto min-w-0 max-w-full">
                    <table class="data-table min-w-[640px] text-xs">
                        <thead>
                            <tr>
                                <th class="w-36">Role / Tim</th>
                                <th class="w-32">Status Akses</th>
                                <th>Peran &amp; Tindakan Terhadap Paper Anda</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="bg-orange/10 font-bold">
                                <td class="font-extrabold text-navy">👤 Author (Anda)</td>
                                <td><span class="badge badge-success text-[10px]">Publik</span></td>
                                <td class="leading-relaxed">Mengunggah naskah (`.docx`/`.zip`), memantau *Live Checklist Results*, mengedit detail pendaftaran, serta mengirim file revisi.</td>
                            </tr>
                            <tr>
                                <td class="font-extrabold text-navy">✍️ Editorial (Editor PIC)</td>
                                <td><span class="badge badge-primary text-[10px]">Staf Conference</span></td>
                                <td class="leading-relaxed">Memeriksa kelayakan 16 format standar IEEE, memberikan petunjuk perbaikan, berkomunikasi via Email &amp; WhatsApp, serta mengonfirmasi revisi.</td>
                            </tr>
                            <tr>
                                <td class="font-extrabold text-navy">🔍 Reviewer (Reviewer PIC)</td>
                                <td><span class="badge badge-primary text-[10px]">Staf Conference</span></td>
                                <td class="leading-relaxed">Memeriksa sertifikasi IEEE PDF eXpress, mencatat kendala EDAS, dan memberikan persetujuan akhir publikasi.</td>
                            </tr>
                            <tr>
                                <td class="font-extrabold text-navy">🏛️ Admin &amp; Superadmin</td>
                                <td><span class="badge badge-warning text-[10px]">Pengelola Sistem</span></td>
                                <td class="leading-relaxed">Menyiapkan form conference, mengelola storage penyimpanan naskah, dan mengawasi jalannya kegiatan conference.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <!-- Main Step by Step Manual Sections -->
        <section class="space-y-6">
            <!-- Summary Step Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card p-5 border-l-4 border-l-orange space-y-2">
                    <span class="badge badge-primary text-[10px] font-black">Langkah 1</span>
                    <h3 class="font-black text-navy text-base">Form Submission</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">Mengisi Paper ID, data author &amp; co-authors, serta mengunggah file naskah editable (DOCX / LaTeX ZIP).</p>
                </div>
                <div class="card p-5 border-l-4 border-l-navy space-y-2">
                    <span class="badge badge-warning text-[10px] font-black">Langkah 2</span>
                    <h3 class="font-black text-navy text-base">Portal Token Rahasia</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">Menerima tautan portal rahasia via email untuk memantau tahapan status paper secara real-time.</p>
                </div>
                <div class="card p-5 border-l-4 border-l-emerald-500 space-y-2">
                    <span class="badge badge-success text-[10px] font-black">Langkah 3</span>
                    <h3 class="font-black text-navy text-base">Live Checklist &amp; Revisi</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">Melihat hasil 16 checklist IEEE dari Editor, mengunggah naskah revisi baru dan PDF petunjuk revisi.</p>
                </div>
            </div>

            <!-- Detailed Step 1 -->
            <div class="card p-6 sm:p-8 space-y-4">
                <div class="border-b border-navy/10 pb-3 flex items-center justify-between">
                    <h2 class="text-xl font-black text-navy flex items-center gap-2">
                        <span class="size-7 rounded-full bg-orange text-white text-xs grid place-items-center font-black">1</span>
                        <span>Pengajuan Naskah Baru (Public Submission Form)</span>
                    </h2>
                    <span class="badge badge-primary text-xs font-bold">Langkah Awal</span>
                </div>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Setiap conference yang diselenggarakan memiliki halaman pengajuan publik sendiri dengan format URL: <code>/{conference-slug}/submit</code>.
                </p>
                <div class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-navy/10 space-y-3 text-xs sm:text-sm text-slate-800">
                    <strong class="text-navy block font-bold text-sm">📋 Panduan Pengisian Form Submission:</strong>
                    <ul class="list-disc pl-5 space-y-2">
                        <li><strong>Paper ID Conference:</strong> Masukkan nomor ID resmi naskah Anda dari sistem registrasi konferensi (misal nomor EDAS ID atau kode registrasi).</li>
                        <li><strong>Data Corresponding Author:</strong> Masukkan Nama Lengkap, Email Aktif, dan Nomor Telepon/WhatsApp (gunakan pemilih kode negara internasional yang tersedia).</li>
                        <li><strong>Data Co-Authors (Penulis Pendamping):</strong> Jika paper ditulis bersama anggota tim lain, klik tombol <em>+ Tambah Co-Author</em> lalu isi Nama, Email, dan Afiliasi masing-masing.</li>
                        <li><strong>Upload File Naskah Editable:</strong> Unggah file naskah format Microsoft Word (<code>.docx</code>) atau arsip sumber LaTeX (<code>.zip</code>). Batas maksimal ukuran file sesuai ketentuan conference (default 10 MB).</li>
                        <li><strong>Verifikasi Security Captcha:</strong> Selesaikan centang Cloudflare Turnstile jika diaktifkan oleh panitia.</li>
                    </ul>
                </div>
            </div>

            <!-- Detailed Step 2 -->
            <div class="card p-6 sm:p-8 space-y-4">
                <div class="border-b border-navy/10 pb-3 flex items-center justify-between">
                    <h2 class="text-xl font-black text-navy flex items-center gap-2">
                        <span class="size-7 rounded-full bg-navy text-white text-xs grid place-items-center font-black">2</span>
                        <span>Akses Portal Rahasia Author (Token Portal)</span>
                    </h2>
                    <span class="badge badge-warning text-xs font-bold">Tanpa Password</span>
                </div>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Setelah berhasil melakukan submission, Anda tidak perlu mendaftar akun dengan password! Sistem otomatis menerbitkan **Token Portal Rahasia** yang dikirimkan ke Email &amp; WhatsApp Anda. Format URL portal: <code>/submission/access/{token}</code>.
                </p>
                <div class="bg-amber-50/60 p-4 sm:p-5 rounded-2xl border border-orange/20 space-y-3 text-xs sm:text-sm text-slate-800">
                    <strong class="text-navy block font-bold text-sm">🔑 Fitur yang Tersedia di Portal Author:</strong>
                    <ul class="list-disc pl-5 space-y-2">
                        <li><strong>Monitoring Progress Timeline:</strong> Memantau progres paper secara langsung (Status: <em>Submitted &rarr; Validated &rarr; Editorial Review &rarr; Reviewer Review &rarr; Ready for EDAS &rarr; Done</em>).</li>
                        <li><strong>Pembaruan Detail Submission:</strong> Mengedit kembali judul paper, email, nomor telepon, atau susunan nama co-authors jika terdapat kesalahan ketik.</li>
                        <li><strong>Unduh Versi Dokumen:</strong> Memeriksa dan mengunduh kembali file-file naskah versi terdahulu yang pernah diunggah.</li>
                    </ul>
                </div>
            </div>

            <!-- Detailed Step 3 -->
            <div class="card p-6 sm:p-8 space-y-4">
                <div class="border-b border-navy/10 pb-3 flex items-center justify-between">
                    <h2 class="text-xl font-black text-navy flex items-center gap-2">
                        <span class="size-7 rounded-full bg-emerald-600 text-white text-xs grid place-items-center font-black">3</span>
                        <span>Pemantauan Live Checklist &amp; Pengunggahan Revisi</span>
                    </h2>
                    <span class="badge badge-success text-xs font-bold">Proses Revisi</span>
                </div>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Apabila Tim Editorial meminta perbaikan naskah, Anda akan menerima email notifikasi. Saat portal dibuka, kartu **Live Checklist Hasil Pemeriksaan IEEE** dari Editor akan tampil secara otomatis.
                </p>
                <div class="bg-emerald-50/50 p-4 sm:p-5 rounded-2xl border border-emerald-200 space-y-3 text-xs sm:text-sm text-slate-800">
                    <strong class="text-navy block font-bold text-sm">📝 Langkah Mengirimkan Naskah Revisi:</strong>
                    <ol class="list-decimal pl-5 space-y-2">
                        <li>Pelajari item-item checklist yang belum dicentang / ditandai perlu perbaikan oleh Editor.</li>
                        <li>Buka file naskah di komputer Anda, lalu perbaiki format naskah sesuai catatan instruksi dari Editor.</li>
                        <li>Buka bagian <strong>Unggah Revisi Naskah</strong> pada Portal Author Anda.</li>
                        <li>Pilih file naskah editable versi terbaru (<code>.docx</code> / <code>.zip</code>).</li>
                        <li><em>(Sangat Disarankan)</em> Unggah file <strong>PDF Petunjuk / Tanggapan Revisi</strong> yang menjelaskan poin-poin perbaikan yang telah Anda sesuaikan.</li>
                        <li>Klik <strong>Kirim Naskah Revisi</strong>. Status paper Anda akan otomatis kembali ke Tim Editorial untuk diperiksa ulang.</li>
                    </ol>
                </div>
            </div>
        </section>
    </div>
</x-layouts.public>
