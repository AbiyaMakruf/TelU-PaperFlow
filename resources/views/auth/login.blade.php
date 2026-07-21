<x-layouts.guest title="Login · Paperflow">
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-[.2em] text-orange">Selamat datang</p>
        <h1 class="mt-2 text-3xl font-black text-navy">Masuk ke Paperflow</h1>
        <p class="mt-2 text-sm leading-6 text-muted">Gunakan akun yang dibuat oleh superadmin.</p>
    </div>
    <x-flash />
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <div>
            <label class="form-label" for="login">Username atau email</label>
            <input class="form-input" id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" placeholder="username atau nama@organisasi.id">
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label class="form-label" for="password">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs font-bold text-orange hover:text-navy">Lupa password?</a>
            </div>
            <input class="form-input" id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <label class="flex items-center gap-3 text-sm text-muted">
            <input type="checkbox" name="remember" class="size-4 rounded border-navy/20 text-orange focus:ring-orange">
            Ingat saya
        </label>
        <button class="btn btn-primary w-full" type="submit">Masuk</button>
    </form>
</x-layouts.guest>
