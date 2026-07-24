<x-layouts.guest title="New Password · Paperflow">
    <h1 class="text-3xl font-black text-navy">Create New Password</h1>
    <p class="mt-2 mb-7 text-sm text-muted">Use a unique password of at least 8 characters.</p>
    <x-flash />
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label class="form-label" for="email">Email Address</label>
            <input class="form-input" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required>
        </div>
        <div>
            <label class="form-label" for="password">New Password</label>
            <x-input-password id="password" name="password" required placeholder="Minimum 8 characters" />
        </div>
        <div>
            <label class="form-label" for="password_confirmation">Confirm New Password</label>
            <x-input-password id="password_confirmation" name="password_confirmation" required placeholder="Retype new password" />
        </div>
        <button class="btn btn-primary w-full">Save New Password</button>
    </form>
</x-layouts.guest>
