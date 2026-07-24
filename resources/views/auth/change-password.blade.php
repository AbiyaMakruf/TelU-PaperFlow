<x-layouts.guest title="Ganti password · Paperflow">
    <h1 class="text-3xl font-black text-navy">Amankan akun Anda</h1>
    <p class="mt-2 mb-7 text-sm leading-6 text-muted">Lengkapi email dan ganti password sementara sebelum membuka workspace.</p>
    <x-flash />
    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label class="form-label" for="current_password">Password sementara</label>
            <x-input-password id="current_password" name="current_password" required placeholder="••••••••" />
        </div>
        <div>
            <label class="form-label" for="email">Email aktif</label>
            <input class="form-input" id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required autocomplete="email">
            <p class="mt-1 text-xs text-muted">Email ini dapat digunakan untuk login dan pemulihan password.</p>
        </div>
        <div>
            <label class="form-label" for="password">Password baru</label>
            <x-input-password id="password" name="password" required placeholder="Minimal 8 karakter" />
        </div>
        <div>
            <label class="form-label" for="password_confirmation">Konfirmasi password</label>
            <x-input-password id="password_confirmation" name="password_confirmation" required placeholder="Ketik ulang password baru" />
        </div>
        <button class="btn btn-primary w-full">Perbarui password</button>
    </form>
</x-layouts.guest>
