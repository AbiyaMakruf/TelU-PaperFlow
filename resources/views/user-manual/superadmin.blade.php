<x-layouts.app title="User Manual Superadmin - Paperflow" heading="Panduan Superadmin">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'superadmin'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-rose-600">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-danger text-xs font-black mb-2">Akses Penuh Superadmin</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Superadmin (Pengelola Sistem Paperflow)</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Panduan operasional penuh pembuatan pengguna, provisioning conference, monitoring infrastruktur, audit log, dan impersonasi akun.</p>
            </div>

            <!-- Fitur 1: Manajemen Pengguna -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>Manajemen Pengguna (`/admin/users`)</span>
                </h3>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Pembuatan Akun Username-Only:</strong> Superadmin dapat membuat akun pengguna baru hanya dengan memasukkan <strong>Nama Lengkap</strong> dan <strong>Username</strong>.</li>
                        <li><strong>Password Sementara (`user1234`):</strong> Pengguna baru otomatis menerima password sementara `user1234`. Pada login pertama kali, sistem memaksa pengguna memasukkan Email pribadi dan memilih Password baru (minimal 8 karakter).</li>
                        <li><strong>Reset Password Pengguna:</strong> Memulihkan password pengguna yang lupa ke password default `user1234`.</li>
                        <li><strong>CLI Bootstrap Superadmin:</strong> Perintah terminal <code>php artisan paperflow:make-superadmin username --email=admin@example.com --name="Super Admin"</code>.</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 2: Provisioning Conference Baru -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Pembuatan Conference Baru (`/conferences/create`)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Saat Superadmin membuat conference baru, layanan <code>ConferenceProvisioner</code> akan otomatis melakukan provisioning:
                </p>
                <div class="bg-amber-50/50 p-4 rounded-xl border border-orange/20 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Membuat 16 item standar IEEE Editorial Compliance Checklist.</li>
                        <li>Membuat item standar IEEE Reviewer Checklist.</li>
                        <li>Menyediakan template email HTML branded standar.</li>
                        <li>Membuka form submission awal versi 1.</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 3: Monitoring & Audit Logs Hub -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Monitoring Operasional &amp; Audit Logs (`/monitoring`)</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Status Sistem &amp; Storage:</strong> Menampilkan status koneksi PostgreSQL Supabase (driver, host, latency, total record per tabel), status PHP `ext-zip`, serta statistik penyimpanan file.</li>
                        <li><strong>Failed Queue Jobs &amp; Error Logs:</strong> Meninjau antrean email yang gagal dikirim, pesan exception Laravel, serta tombol <strong>Retry Job</strong>.</li>
                        <li><strong>Audit Log Aktivitas Staf:</strong> Meninjau seluruh aktivitas perubahan data penting yang dilakukan staf di seluruh conference dengan viewer JSON diff interaktif.</li>
                    </ul>
                </div>
            </div>

            <!-- Fitur 4: User Impersonation -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>4.</span> <span>Impersonasi Pengguna (`/admin/users/{user}/impersonate`)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Superadmin dapat "menyamar" sebagai pengguna lain untuk mendiagnosis kendala teknis atau melihat tampilan sudut pandang staf lain secara langsung.
                </p>
                <div class="bg-rose-50/50 p-4 rounded-xl border border-rose-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Buka menu Pengguna &rarr; Klik <code>Impersonate</code> di samping nama pengguna target.</li>
                        <li>Sistem akan beralih ke sesi pengguna tersebut secara aman. Banner penanda impersonasi berwarna kuning akan muncul di bagian atas aplikasi.</li>
                        <li>Klik <strong>Kembali ke Akun Superadmin</strong> di banner atas untuk kembali ke akun utama Anda tanpa perlu memasukkan password lagi.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
