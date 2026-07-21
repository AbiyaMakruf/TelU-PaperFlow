<x-layouts.guest title="Ganti password · Paperflow">
    <h1 class="text-3xl font-black text-navy">Amankan akun Anda</h1>
    <p class="mt-2 mb-7 text-sm leading-6 text-muted">Anda wajib mengganti password sementara sebelum membuka workspace.</p>
    <x-flash />
    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5">
        @csrf @method('PUT')
        <div><label class="form-label" for="current_password">Password sementara</label><input class="form-input" id="current_password" type="password" name="current_password" required></div>
        <div><label class="form-label" for="password">Password baru</label><input class="form-input" id="password" type="password" name="password" required></div>
        <div><label class="form-label" for="password_confirmation">Konfirmasi password</label><input class="form-input" id="password_confirmation" type="password" name="password_confirmation" required></div>
        <button class="btn btn-primary w-full">Perbarui password</button>
    </form>
</x-layouts.guest>
