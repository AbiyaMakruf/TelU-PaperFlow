<x-layouts.app title="Edit pengguna · Paperflow" heading="Edit pengguna">
    <div class="max-w-2xl"><a href="{{ route('admin.users.index') }}" class="back-link">← Kembali</a><h1 class="page-title mt-4">{{ $user->name }}</h1><p class="page-subtitle">Perbarui profil dan status akses pengguna.</p>
        <x-flash />
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card mt-7 space-y-5 p-6">@csrf @method('PUT')
            <div><label class="form-label" for="name">Nama lengkap</label><input class="form-input" id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
            <div><label class="form-label" for="username">Username</label><input class="form-input" id="username" name="username" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="50"></div>
            <div><label class="form-label" for="email">Email</label><input class="form-input" id="email" type="email" name="email" value="{{ old('email', $user->email) }}"><p class="mt-1 text-xs text-muted">Dapat kosong sebelum pengguna menyelesaikan login pertama.</p></div>
            <label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))><span><strong>Akun aktif</strong><small>Pengguna nonaktif akan dikeluarkan dari sesi.</small></span></label>
            <label class="check-row"><input type="checkbox" name="is_super_admin" value="1" @checked(old('is_super_admin', $user->is_super_admin))><span><strong>Superadmin</strong><small>Akses penuh ke seluruh Paperflow.</small></span></label>
            <div class="flex flex-wrap justify-between gap-3"><button form="reset-password-form" class="btn btn-secondary" type="submit">Reset ke user1234</button><div class="flex gap-3"><a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Batal</a><button class="btn btn-primary">Simpan</button></div></div>
        </form>
        <form id="reset-password-form" method="POST" action="{{ route('admin.users.reset-password', $user) }}">@csrf</form>
    </div>
</x-layouts.app>
