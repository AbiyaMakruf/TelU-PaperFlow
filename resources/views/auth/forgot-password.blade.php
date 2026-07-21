<x-layouts.guest title="Lupa password · Paperflow">
    <h1 class="text-3xl font-black text-navy">Reset password</h1>
    <p class="mt-2 mb-7 text-sm leading-6 text-muted">Masukkan email akun. Kami akan mengirim tautan pengaturan ulang.</p>
    <x-flash />
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div><label class="form-label" for="email">Email</label><input class="form-input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
        <button class="btn btn-primary w-full">Kirim tautan reset</button>
        <a href="{{ route('login') }}" class="block text-center text-sm font-bold text-orange">Kembali ke login</a>
    </form>
</x-layouts.guest>
