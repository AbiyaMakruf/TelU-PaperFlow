<x-layouts.app :title="'Members · '.$conference->name" heading="Conference Members">
    <div class="w-full space-y-6">
        <x-conference-header :conference="$conference" active="members" />
        <x-flash />
        <form method="POST" action="{{ route('conferences.members.store', $conference) }}" class="card mt-7 grid gap-4 p-5 sm:grid-cols-[1fr_220px_auto]">
            @csrf
            <div>
                <label class="form-label" for="user_id">User</label>
                <select class="form-input" id="user_id" name="user_id" required>
                    <option value="">Select User</option>
                    @foreach($availableUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="role">Role</label>
                <select class="form-input" id="role" name="role">
                    @foreach(\App\Enums\ConferenceRole::cases() as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary self-end">Add Member</button>
        </form>
        <div class="card mt-5 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($conference->memberships->sortBy('user.name') as $member)
                            <tr>
                                <td>
                                    <p class="font-bold text-navy">{{ $member->user->name }}</p>
                                    <p class="text-xs text-muted">{{ $member->user->email }}</p>
                                </td>
                                <td>{{ $member->role->label() }}</td>
                                <td><span class="badge {{ $member->is_active ? 'badge-success' : 'badge-danger' }}">{{ $member->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-right">
                                    @if($member->is_active)
                                        <form method="POST" action="{{ route('conferences.members.destroy', [$conference, $member]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-sm font-bold text-danger">Deactivate</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-muted">No team members assigned yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
