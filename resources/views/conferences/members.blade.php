<x-layouts.app :title="'Anggota '.$conference->name" heading="Anggota conference">
    <div class="max-w-5xl"><a href="{{ route('conferences.show', $conference) }}" class="back-link">← Kembali</a><h1 class="page-title mt-4">Anggota {{ $conference->name }}</h1><p class="page-subtitle">Satu pengguna dapat memiliki role berbeda pada conference lain.</p><x-flash />
        <form method="POST" action="{{ route('conferences.members.store', $conference) }}" class="card mt-7 grid gap-4 p-5 sm:grid-cols-[1fr_220px_auto]">@csrf
            <div><label class="form-label" for="user_id">Pengguna</label><select class="form-input" id="user_id" name="user_id" required><option value="">Pilih pengguna</option>@foreach($availableUsers as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></div>
            <div><label class="form-label" for="role">Role</label><select class="form-input" id="role" name="role">@foreach(\App\Enums\ConferenceRole::cases() as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></div>
            <button class="btn btn-primary self-end">Tambahkan</button>
        </form>
        <div class="card mt-5 overflow-hidden"><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Pengguna</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($conference->memberships->sortBy('user.name') as $member)
                <tr><td><p class="font-bold text-navy">{{ $member->user->name }}</p><p class="text-xs text-muted">{{ $member->user->email }}</p></td><td>{{ $member->role->label() }}</td><td><span class="badge {{ $member->is_active ? 'badge-success' : 'badge-danger' }}">{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="text-right">@if($member->is_active)<form method="POST" action="{{ route('conferences.members.destroy', [$conference, $member]) }}">@csrf @method('DELETE')<button class="text-sm font-bold text-danger">Nonaktifkan</button></form>@endif</td></tr>
            @empty<tr><td colspan="4" class="py-10 text-center text-muted">Belum ada anggota.</td></tr>@endforelse
        </tbody></table></div></div>
    </div>
</x-layouts.app>
