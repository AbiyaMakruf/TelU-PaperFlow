<x-layouts.app title="New User · Paperflow" heading="New User">
    <div class="max-w-2xl">
        <a href="{{ route('admin.users.index') }}" class="back-link">&larr; Back</a>
        <h1 class="page-title mt-4">Create Staff Account</h1>
        <p class="page-subtitle">First-time users log in using their username and the default initial password <strong>user1234</strong>.</p>
        <x-flash />
        <form method="POST" action="{{ route('admin.users.store') }}" class="card mt-7 space-y-5 p-6">
            @csrf
            <div>
                <label class="form-label" for="name">Full Name</label>
                <input class="form-input" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. John Doe">
            </div>
            <div>
                <label class="form-label" for="username">Username</label>
                <input class="form-input" id="username" name="username" value="{{ old('username') }}" required minlength="3" maxlength="50" autocomplete="off" placeholder="johndoe">
                <p class="mt-1 text-xs text-muted">Use letters, numbers, hyphens, or underscores without spaces.</p>
            </div>
            <label class="check-row">
                <input type="checkbox" name="is_super_admin" value="1" @checked(old('is_super_admin'))>
                <span><strong>Superadmin Access</strong><small>Grant global administrative access across all conferences and users.</small></span>
            </label>
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Cancel</a>
                <button class="btn btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</x-layouts.app>
