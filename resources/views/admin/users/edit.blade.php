<x-layouts.app title="Edit User · Paperflow" heading="Edit User">
    <div class="max-w-2xl">
        <a href="{{ route('admin.users.index') }}" class="back-link">&larr; Back</a>
        <h1 class="page-title mt-4">{{ $user->name }}</h1>
        <p class="page-subtitle">Update user profile details and access permissions.</p>
        <x-flash />
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card mt-7 space-y-5 p-6">
            @csrf
            @method('PUT')
            <div>
                <label class="form-label" for="name">Full Name</label>
                <input class="form-input" id="name" name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div>
                <label class="form-label" for="username">Username</label>
                <input class="form-input" id="username" name="username" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="50">
            </div>
            <div>
                <label class="form-label" for="email">Email Address</label>
                <input class="form-input" id="email" type="email" name="email" value="{{ old('email', $user->email) }}">
                <p class="mt-1 text-xs text-muted">Can be left blank until the user completes their first login.</p>
            </div>
            <label class="check-row">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                <span><strong>Account Active</strong><small>Inactive users will be logged out of active sessions.</small></span>
            </label>
            <label class="check-row">
                <input type="checkbox" name="is_super_admin" value="1" @checked(old('is_super_admin', $user->is_super_admin))>
                <span><strong>Superadmin Access</strong><small>Full access to all Paperflow management features.</small></span>
            </label>
            <div class="flex flex-wrap justify-between gap-3">
                <button form="reset-password-form" class="btn btn-secondary" type="submit">Reset to user1234</button>
                <div class="flex gap-3">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
        <form id="reset-password-form" method="POST" action="{{ route('admin.users.reset-password', $user) }}">@csrf</form>
    </div>
</x-layouts.app>
