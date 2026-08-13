<x-layouts.guest title="First Time Setup · Paperflow">
    <div class="mb-5">
        <p class="text-sm font-bold uppercase tracking-[.2em] text-orange">First Time Setup</p>
        <h1 class="mt-2 text-3xl font-black text-navy">Complete Your Staff Profile</h1>
        <p class="mt-2 text-sm leading-6 text-muted">Please set up your official email address, mobile/WhatsApp number, and choose your new password before entering the workspace.</p>
    </div>

    <!-- Mandatory Contact Info Explanation Notice -->
    <div class="mb-6 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-xs leading-relaxed text-sky-950 shadow-xs">
        <div class="flex items-start gap-2.5">
            <span class="text-base leading-none shrink-0">ℹ️</span>
            <div>
                <strong class="font-bold text-sky-950 block mb-1">Mengapa Email &amp; Nomor WhatsApp Wajib Diisi?</strong>
                <span>Email dan nomor WhatsApp Anda akan ditampilkan secara otomatis sebagai <strong>Informasi Kontak PIC Editor</strong> pada <strong>Portal Author</strong>. Jika penulis (author) membutuhkan bantuan atau memiliki pertanyaan seputar naskah mereka, author dapat langsung menghubungi Anda melalui kontak ini.</span>
            </div>
        </div>
    </div>

    <x-flash />

    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="form-label" for="email">Active Email Address <span class="text-rose-600">*</span></label>
            <input class="form-input" id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required autocomplete="email" placeholder="name@organization.ac.id">
            <p class="mt-1 text-xs text-muted">Used for login, system notifications, and displayed to authors.</p>
        </div>

        <div>
            <label class="form-label" for="whatsapp_number">Mobile / WhatsApp Number <span class="text-rose-600">*</span></label>
            <div class="grid grid-cols-[110px_1fr] sm:grid-cols-[130px_1fr] gap-2">
                <select class="form-input text-xs" name="whatsapp_country_code" required>
                    @foreach($countryCodes as $code => $label)
                        <option value="{{ $code }}" @selected(old('whatsapp_country_code', auth()->user()->whatsapp_country_code ?? '+62') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="form-input text-xs" id="whatsapp_number" type="text" name="whatsapp_number" value="{{ old('whatsapp_number', auth()->user()->whatsapp_number) }}" required placeholder="e.g. 81234567890">
            </div>
            <p class="mt-1 text-xs text-muted">Enter number without leading zero (e.g. 81234567890).</p>
        </div>

        <div>
            <label class="form-label" for="password">New Password <span class="text-rose-600">*</span></label>
            <x-input-password id="password" name="password" required placeholder="Minimum 8 characters" />
        </div>

        <div>
            <label class="form-label" for="password_confirmation">Confirm New Password <span class="text-rose-600">*</span></label>
            <x-input-password id="password_confirmation" name="password_confirmation" required placeholder="Retype new password" />
        </div>

        <button class="btn btn-primary w-full">Save Profile &amp; Open Workspace</button>
    </form>
</x-layouts.guest>
