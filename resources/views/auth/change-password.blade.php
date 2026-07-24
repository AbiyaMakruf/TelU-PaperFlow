<x-layouts.guest title="Change Password · Paperflow">
    <h1 class="text-3xl font-black text-navy">Secure Your Account</h1>
    <p class="mt-2 mb-7 text-sm leading-6 text-muted">Please provide your active email address and replace your temporary password before accessing the workspace.</p>
    <x-flash />
    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label class="form-label" for="current_password">Temporary Password</label>
            <x-input-password id="current_password" name="current_password" required placeholder="••••••••" />
        </div>
        <div>
            <label class="form-label" for="email">Active Email Address</label>
            <input class="form-input" id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required autocomplete="email">
            <p class="mt-1 text-xs text-muted">This email address will be used for account login and password recovery.</p>
        </div>
        <div>
            <label class="form-label" for="password">New Password</label>
            <x-input-password id="password" name="password" required placeholder="Minimum 8 characters" />
        </div>
        <div>
            <label class="form-label" for="password_confirmation">Confirm New Password</label>
            <x-input-password id="password_confirmation" name="password_confirmation" required placeholder="Retype new password" />
        </div>
        <button class="btn btn-primary w-full">Update Password</button>
    </form>
</x-layouts.guest>
