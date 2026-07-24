<x-layouts.app title="Profil Saya" heading="Profil Saya">
    <div class="mx-auto max-w-4xl space-y-8">
        <div>
            <p class="eyebrow">Personal Identity &amp; Account Settings</p>
            <h1 class="page-title">Pengaturan Profil &amp; Akun</h1>
            <p class="page-subtitle">Kelola informasi identitas editorial, username, alamat email dengan verifikasi OTP, dan kata sandi akun Anda.</p>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 text-sm text-emerald-800 shadow-sm flex items-center gap-3">
                <span class="text-xl">✅</span>
                <div class="font-bold">{{ session('success') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-800 shadow-sm">
                <div class="font-extrabold flex items-center gap-2 mb-1">
                    <span>⚠️</span> Terjadi kesalahan input:
                </div>
                <ul class="list-disc pl-5 space-y-0.5 text-xs">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- CARD 1: INFORMASI PROFIL EDITORIAL -->
        <div class="card p-6 sm:p-7">
            <div class="border-b border-navy/10 pb-4 mb-6">
                <h2 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>👤</span> Informasi Identitas Editorial
                </h2>
                <p class="text-xs text-muted mt-0.5">Identitas ini digunakan pada tanda tangan email otomatis dan tautan WhatsApp editorial.</p>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')

                <label class="sm:col-span-2">
                    <span class="form-label">Nama Lengkap *</span>
                    <input class="form-input" name="name" value="{{ old('name', $user->name) }}" required placeholder="Contoh: Dr. Budi Santoso, M.T.">
                </label>

                <label>
                    <span class="form-label">Jabatan / Peran Publikasi</span>
                    <input class="form-input" name="job_title" value="{{ old('job_title', $user->job_title) }}" placeholder="Contoh: Publication Chair / Editor">
                </label>

                <label>
                    <span class="form-label">Institusi / Afiliasi</span>
                    <input class="form-input" name="affiliation" value="{{ old('affiliation', $user->affiliation) }}" placeholder="Contoh: Telkom University">
                </label>

                <label>
                    <span class="form-label">Kode Negara WhatsApp</span>
                    <select class="form-input" name="whatsapp_country_code">
                        <option value="">Pilih Kode Negara...</option>
                        @foreach($countryCodes as $code => $label)
                            <option value="{{ $code }}" @selected(old('whatsapp_country_code', $user->whatsapp_country_code) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="form-label">Nomor WhatsApp</span>
                    <input class="form-input" name="whatsapp_number" value="{{ old('whatsapp_number', $user->whatsapp_number) }}" placeholder="81234567890">
                </label>

                <div class="sm:col-span-2 pt-2">
                    <button class="btn btn-primary w-full sm:w-auto">💾 Simpan Informasi Profil</button>
                </div>
            </form>
        </div>

        <!-- CARD 2: PENGATURAN USERNAME (DENGAN CEK KETERSEDIAAN & MODAL KONFIRMASI) -->
        <div x-data="{
            username: '{{ old('username', $user->username) }}',
            initialUsername: '{{ $user->username }}',
            checking: false,
            available: null,
            checkMessage: '',
            showConfirmModal: false,
            async checkAvailability() {
                if (!this.username || this.username.length < 3) {
                    this.available = false;
                    this.checkMessage = 'Username minimal 3 karakter.';
                    return;
                }
                this.checking = true;
                this.checkMessage = '';
                try {
                    const res = await fetch('{{ route('profile.username.check') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ username: this.username })
                    });
                    const data = await res.json();
                    this.available = data.available;
                    this.checkMessage = data.message;
                } catch (e) {
                    this.available = false;
                    this.checkMessage = 'Gagal memeriksa ketersediaan username.';
                } finally {
                    this.checking = false;
                }
            },
            async trySubmit() {
                if (this.username === this.initialUsername) return;
                if (this.available !== true) {
                    await this.checkAvailability();
                }
                if (this.available === true) {
                    this.showConfirmModal = true;
                }
            }
        }" class="card p-6 sm:p-7">
            <div class="border-b border-navy/10 pb-4 mb-6">
                <h2 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>🆔</span> Pengaturan Username
                </h2>
                <p class="text-xs text-muted mt-0.5">Username digunakan untuk masuk (login) ke sistem Paperflow.</p>
            </div>

            <form id="usernameForm" method="POST" action="{{ route('profile.username.update') }}" @submit.prevent="trySubmit()" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <span class="form-label">Username Akun *</span>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mt-1">
                        <input class="form-input flex-1 font-mono font-bold text-navy" 
                               name="username" 
                               x-model="username" 
                               @input="available = null; checkMessage = ''"
                               required 
                               minlength="3" 
                               placeholder="username_anda">
                        <button type="button" 
                                @click="checkAvailability()" 
                                :disabled="checking || !username || username === initialUsername"
                                class="btn btn-secondary text-xs shrink-0 py-2.5">
                            <span x-show="!checking">🔍 Cek Ketersediaan</span>
                            <span x-show="checking">Memeriksa...</span>
                        </button>
                    </div>

                    <!-- Availability Status Badge -->
                    <div class="mt-2 text-xs font-bold">
                        <template x-if="username === initialUsername">
                            <span class="text-slate-500">🔵 Ini adalah username Anda saat ini.</span>
                        </template>
                        <template x-if="username !== initialUsername && checkMessage">
                            <span :class="available ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-rose-700 bg-rose-50 border-rose-200'" class="px-2.5 py-1 rounded-lg border inline-block">
                                <span x-text="available ? '✓ ' : '✕ '"></span>
                                <span x-text="checkMessage"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            :disabled="username === initialUsername || available === false" 
                            class="btn btn-primary w-full sm:w-auto">
                        ✏️ Ubah Username
                    </button>
                </div>
            </form>

            <!-- CONFIRMATION POPUP MODAL FOR USERNAME CHANGE -->
            <div x-show="showConfirmModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy/60 backdrop-blur-sm p-4">
                <div class="card w-full max-w-md p-6 bg-white shadow-2xl space-y-4 text-left" @click.outside="showConfirmModal = false">
                    <div class="flex items-center gap-3 border-b border-navy/10 pb-3">
                        <span class="text-2xl">⚠️</span>
                        <div>
                            <h3 class="text-base font-black text-navy">Konfirmasi Perubahan Username</h3>
                            <p class="text-xs text-muted">Tindakan ini memerlukan verifikasi Anda</p>
                        </div>
                    </div>

                    <p class="text-sm text-slate-700 leading-relaxed">
                        Apakah Anda yakin ingin mengubah username Anda dari <strong class="text-navy font-mono" x-text="initialUsername"></strong> menjadi <strong class="text-emerald-700 font-mono" x-text="username"></strong>?
                    </p>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900 font-medium">
                        📌 <strong>Catatan:</strong> Setelah diubah, Anda harus menggunakan username baru ini (<strong><span x-text="username"></span></strong>) untuk masuk ke akun Paperflow berikutnya.
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="showConfirmModal = false" class="btn btn-secondary text-xs">Batal</button>
                        <button type="button" @click="$req = document.getElementById('usernameForm'); $req.submit()" class="btn btn-primary text-xs">Ya, Ubah Username</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 3: PENGATURAN EMAIL (DENGAN VERIFIKASI OTP 4-DIGIT BAHASA INGGRIS) -->
        <div x-data="{
            showOtpForm: {{ session('otp_sent') ? 'true' : 'false' }},
            pendingEmail: '{{ session('pending_new_email', '') }}',
            otpCode: '',
            loading: false,
            otpError: ''
        }" class="card p-6 sm:p-7">
            <div class="border-b border-navy/10 pb-4 mb-6">
                <h2 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>✉️</span> Pengaturan Alamat Email
                </h2>
                <p class="text-xs text-muted mt-0.5">Alamat email digunakan untuk notifikasi sistem dan pemulihan kata sandi.</p>
            </div>

            <div class="mb-5 rounded-xl bg-slate-50 border border-slate-200 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                <div>
                    <span class="text-slate-500 font-medium block">Alamat Email Saat Ini:</span>
                    <span class="text-base font-black text-navy mt-0.5 block font-mono">{{ $user->email }}</span>
                </div>
                <span class="badge badge-success text-[11px] px-3 py-1 font-bold">✓ Terverifikasi</span>
            </div>

            <!-- REQUEST OTP FORM -->
            <form method="POST" action="{{ route('profile.email.request-otp') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="form-label">Alamat Email Baru *</span>
                        <input type="email" class="form-input" name="new_email" value="{{ old('new_email', session('pending_new_email')) }}" required placeholder="email_baru@example.com">
                    </label>

                    <label>
                        <span class="form-label">Password Saat Ini (Konfirmasi Keamanan) *</span>
                        <input type="password" class="form-input" name="password" required placeholder="••••••••">
                    </label>
                </div>

                <div class="pt-2">
                    <button class="btn btn-primary w-full sm:w-auto">
                        📩 Kirim Kode OTP Ke Email Baru
                    </button>
                </div>
            </form>

            <!-- OTP VERIFICATION MODAL / SECTION -->
            <template x-if="showOtpForm || '{{ session('otp_sent') }}'">
                <div class="mt-6 border-t border-navy/10 pt-6">
                    <div class="rounded-2xl border-2 border-orange-400/40 bg-orange-50/60 p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl">🔑</span>
                            <div>
                                <h3 class="text-base font-black text-navy">Masukkan Kode Verifikasi OTP (4-Digit)</h3>
                                <p class="text-xs text-slate-600">Kode OTP 4-digit dalam bahasa Inggris telah dikirimkan ke email baru Anda: <strong class="text-navy font-mono" x-text="pendingEmail || '{{ session('pending_new_email') }}'"></strong></p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('profile.email.verify-otp') }}" class="space-y-4 max-w-sm">
                            @csrf
                            <div>
                                <span class="form-label text-xs">Kode Verifikasi (4-Digit OTP):</span>
                                <input name="otp" 
                                       type="text" 
                                       maxlength="4" 
                                       required 
                                       pattern="[0-9]{4}" 
                                       class="form-input mt-1 text-center font-mono text-2xl font-black tracking-[0.5em] text-navy uppercase bg-white border-2 border-orange-300 focus:border-orange-500" 
                                       placeholder="0000">
                                <span class="text-[11px] text-muted block mt-1">Kode berlaku selama 15 menit. Silakan periksa folder Inbox atau Spam.</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="submit" class="btn btn-primary text-xs font-bold py-2.5">
                                    ✅ Verifikasi &amp; Simpan Email Baru
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>

        <!-- CARD 4: UBAH PASSWORD -->
        <div class="card p-6 sm:p-7">
            <div class="border-b border-navy/10 pb-4 mb-6">
                <h2 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>🔒</span> Ubah Kata Sandi (Password)
                </h2>
                <p class="text-xs text-muted mt-0.5">Perbarui kata sandi akun Anda secara berkala untuk menjaga keamanan akun.</p>
            </div>

            <form method="POST" action="{{ route('profile.password.update') }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')

                <label class="sm:col-span-2">
                    <span class="form-label">Password Saat Ini (Old Password) *</span>
                    <input type="password" class="form-input" name="old_password" required placeholder="Masukkan password saat ini">
                </label>

                <label>
                    <span class="form-label">Password Baru (New Password) *</span>
                    <input type="password" class="form-input" name="new_password" required minlength="8" placeholder="Minimal 8 karakter">
                </label>

                <label>
                    <span class="form-label">Konfirmasi Password Baru *</span>
                    <input type="password" class="form-input" name="new_password_confirmation" required minlength="8" placeholder="Ketik ulang password baru">
                </label>

                <div class="sm:col-span-2 pt-2">
                    <button class="btn btn-primary w-full sm:w-auto">🔒 Simpan Password Baru</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
