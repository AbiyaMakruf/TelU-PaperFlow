<x-layouts.guest title="Password baru · Paperflow">
    <h1 class="text-3xl font-black text-navy">Buat password baru</h1>
    <p class="mt-2 mb-7 text-sm text-muted">Gunakan password unik yang belum pernah digunakan.</p>
    <x-flash />
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div><label class="form-label" for="email">Email</label><input class="form-input" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required></div>
        <div><label class="form-label" for="password">Password baru</label><input class="form-input" id="password" type="password" name="password" required></div>
        <div><label class="form-label" for="password_confirmation">Konfirmasi password</label><input class="form-input" id="password_confirmation" type="password" name="password_confirmation" required></div>
        <button class="btn btn-primary w-full">Simpan password</button>
    </form>
</x-layouts.guest>
