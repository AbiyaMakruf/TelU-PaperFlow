<x-layouts.guest title="Forgot Password · Paperflow">
    <h1 class="text-3xl font-black text-navy">Reset Your Password</h1>
    <p class="mt-2 mb-7 text-sm leading-6 text-muted">Enter your registered account email address. We will send you a password reset link.</p>
    <x-flash />
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div>
            <label class="form-label" for="email">Email Address</label>
            <input class="form-input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@organization.id">
        </div>
        <button class="btn btn-primary w-full">Send Reset Link</button>
        <a href="{{ route('login') }}" class="block text-center text-sm font-bold text-orange hover:text-navy">Back to Login</a>
    </form>
</x-layouts.guest>
