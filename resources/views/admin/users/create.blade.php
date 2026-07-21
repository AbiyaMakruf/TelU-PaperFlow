<x-layouts.app title="Pengguna baru · Paperflow" heading="Pengguna baru">
    <div class="max-w-2xl"><a href="{{ route('admin.users.index') }}" class="back-link">← Kembali</a><h1 class="page-title mt-4">Buat akun staf</h1><p class="page-subtitle">Pengguna login pertama kali dengan username dan password awal <strong>user1234</strong>.</p>
        <x-flash />
        <form method="POST" action="{{ route('admin.users.store') }}" class="card mt-7 space-y-5 p-6">@csrf
            <div><label class="form-label" for="name">Nama lengkap</label><input class="form-input" id="name" name="name" value="{{ old('name') }}" required></div>
            <div><label class="form-label" for="username">Username</label><input class="form-input" id="username" name="username" value="{{ old('username') }}" required minlength="3" maxlength="50" autocomplete="off"><p class="mt-1 text-xs text-muted">Gunakan huruf, angka, tanda hubung, atau underscore tanpa spasi.</p></div>
            <label class="check-row"><input type="checkbox" name="is_super_admin" value="1" @checked(old('is_super_admin'))><span><strong>Superadmin</strong><small>Berikan akses global untuk conference dan pengguna.</small></span></label>
            <div class="flex justify-end gap-3"><a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Batal</a><button class="btn btn-primary">Buat akun</button></div>
        </form>
    </div>
</x-layouts.app>
