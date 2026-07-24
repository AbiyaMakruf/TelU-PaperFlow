<x-layouts.guest title="Password baru · Paperflow">
    <h1 class="text-3xl font-black text-navy">Buat password baru</h1>
    <p class="mt-2 mb-7 text-sm text-muted">Gunakan password unik yang belum pernah digunakan.</p>
    <x-flash />
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label class="form-label" for="email">Email</label>
            <input class="form-input" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required>
        </div>
        <div>
            <label class="form-label" for="password">Password baru</label>
            <x-input-password id="password" name="password" required placeholder="Minimal 8 karakter" />
        </div>
        <div>
            <label class="form-label" for="password_confirmation">Konfirmasi password</label>
            <x-input-password id="password_confirmation" name="password_confirmation" required placeholder="Ketik ulang password baru" />
        </div>
        <button class="btn btn-primary w-full">Simpan password</button>
    </form>
</x-layouts.guest>
