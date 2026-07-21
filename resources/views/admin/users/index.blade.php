<x-layouts.app title="Pengguna · Paperflow" heading="Pengguna">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="eyebrow">Superadmin</p><h1 class="page-title">Kelola pengguna</h1><p class="page-subtitle">Buat akun staf dan kelola akses global.</p></div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">+ Pengguna baru</a>
    </div>
    <form method="GET" class="mt-7 card flex gap-3 p-4">
        <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Cari nama, username, atau email">
        <button class="btn btn-secondary">Cari</button>
    </form>
    <div class="card mt-5 overflow-hidden">
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Pengguna</th><th>Akses</th><th>Conference</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse ($users as $user)
                <tr>
                    <td><p class="font-bold text-navy">{{ $user->name }}</p><p class="text-xs text-muted">{{ $user->username ? '@'.$user->username : 'Belum ada username' }}{{ $user->email ? ' · '.$user->email : '' }}</p></td>
                    <td>{{ $user->is_super_admin ? 'Superadmin' : 'Staf' }}</td>
                    <td>{{ $user->conference_memberships_count }}</td>
                    <td><span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-right flex items-center justify-end gap-3">
                        @if(auth()->id() !== $user->id)
                            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="inline">
                                @csrf
                                <button class="text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg">Impersonate 👤</button>
                            </form>
                        @endif
                        <a class="text-sm font-bold text-orange" href="{{ route('admin.users.edit', $user) }}">Edit</a>
                    </td>
                </tr>
            @empty<tr><td colspan="5" class="py-12 text-center text-muted">Belum ada pengguna.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="mt-5">{{ $users->links() }}</div>
</x-layouts.app>
