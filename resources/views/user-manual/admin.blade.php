<x-layouts.app title="User Manual Admin Conference - Paperflow" heading="Panduan Conference Admin">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'admin'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-orange">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-warning text-xs font-black mb-2">Role Staf Conference Admin</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Conference Admin (Administrator Conference)</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Panduan lengkap penyiapan conference, pembangun form custom, penyedia penyimpanan file, template email, manajemen tim, dan aksi masal paper.</p>
            </div>

            <!-- Fitur 1: Workspace Selector -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>GCP-Style Active Workspace Selector</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Conference Admin dapat berpindah aktif conference melalui pemilih workspace di header atau drawer seluler. Seluruh tampilan paper, performa editor, dan email log akan otomatis tersaring (*scoped*) sesuai workspace yang aktif.
                </p>
            </div>

            <!-- Fitur 2: Form Builder & Public Landing Page -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Form Builder Custom (`/conferences/{id}/form`)</span>
                </h3>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Field Inti Otomatis:</strong> Paper ID, judul paper, nama/email/telepon corresponding author, data co-authors, dan file naskah editable disajikan otomatis oleh sistem.</li>
                        <li><strong>Tambah Field Kustom:</strong> Tambahkan field khusus conference (tipe teks, angka, tanggal, dropdown, radio, checkbox, textarea).</li>
                        <li><strong>Publikasi Form:</strong> Setelah selesai menyusun draft, klik <strong>Publikasikan Form</strong> agar form aktif di URL submission publik.</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 3: Storage Provider Configuration -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Penyimpanan File (`/conferences/{id}/drive`)</span>
                </h3>
                <div class="bg-amber-50/50 p-4 rounded-xl border border-orange/20 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Supabase Storage (Privat Default):</strong> Menyimpan file pada bucket privat Supabase dengan Signed URL yang aman.</li>
                        <li><strong>Google Drive OAuth:</strong> Menghubungkan folder Google Drive resmi conference menggunakan otentikasi OAuth2. Laravel mengurus otorisasi dan streaming unduhan secara aman.</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 4: Custom Checklist IEEE & Email Templates -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>4.</span> <span>Checklist IEEE &amp; Template Email Conference</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Checklist IEEE Editor &amp; Reviewer (`/conferences/{id}/checklists`):</strong> Menambah, mengubah, atau mengaktifkan item pemeriksaan IEEE standar.</li>
                        <li><strong>Template Email Branded (`/conferences/{id}/email-templates`):</strong> Mengedit template email HTML resmi conference, mengatur alamat Default CC conference, melihat live-preview template, serta mengirim uji coba (*Test Send*).</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 5: Kelola Tim & Bulk Actions -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>5.</span> <span>Kelola Anggota Tim &amp; Bulk Actions Paper (`/papers`)</span>
                </h3>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Kelola Tim (`/conferences/{id}/members`):</strong> Menambahkan staf ke dalam conference dengan role Admin, Editorial, Reviewer, atau Viewer.</li>
                        <li><strong>Bulk Assignment:</strong> Menandai beberapa paper di daftar paper untuk menetapkan PIC Editor, PIC Reviewer, format naskah, atau tenggat waktu secara masal.</li>
                        <li><strong>Bulk Status &amp; Download ZIP:</strong> Mengubah status masal (Validasi, Reject, Withdraw) atau mengunduh seluruh naskah editable author yang dipilih dalam 1 paket file ZIP bernama Paper ID.</li>
                        <li><strong>Export Data CSV:</strong> Mengunduh seluruh rekapitulasi data submission conference ke format CSV (`/papers-export.csv`).</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
