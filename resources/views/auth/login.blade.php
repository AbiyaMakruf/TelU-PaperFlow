<x-layouts.guest title="Login · Paperflow">
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-[.2em] text-orange">Welcome Back</p>
        <h1 class="mt-2 text-3xl font-black text-navy">Log in to Paperflow</h1>
        <p class="mt-2 text-sm leading-6 text-muted">Use your account credentials created by the administrator.</p>
    </div>
    <x-flash />
    <div class="mb-6 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-xs leading-relaxed text-sky-900 shadow-xs">
        <div class="flex items-start gap-2.5">
            <span class="text-base leading-none shrink-0">ℹ️</span>
            <div>
                <strong class="font-bold text-sky-950 block mb-0.5">Are you an Author?</strong>
                <span>Authors do not need to log in here. Please access your paper status and upload revisions using your private <strong>Author Portal</strong> link sent to your email.</span>
            </div>
        </div>
    </div>
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <div>
            <label class="form-label" for="login">Username or Email Address</label>
            <input class="form-input" id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" placeholder="username or name@organization.id">
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="form-label mb-0" for="password">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs font-bold text-orange hover:text-navy">Forgot password?</a>
            </div>
            <x-input-password id="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
        </div>
        <label class="flex items-center gap-3 text-sm text-muted">
            <input type="checkbox" name="remember" class="size-4 rounded border-navy/20 text-orange focus:ring-orange">
            Remember me
        </label>
        <button class="btn btn-primary w-full" type="submit">Log In</button>
    </form>
</x-layouts.guest>
