@props(['title' => 'Paperflow'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} &middot; Paperflow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-warm text-ink antialiased">
    <header class="border-b border-white/10 bg-navy text-white">
        <div class="container-page flex min-h-20 items-center justify-between">
            <x-brand class="text-white" />
            <a href="{{ route('login') }}" class="text-sm font-bold text-white/70 hover:text-orange">Login tim editorial</a>
        </div>
    </header>
    <main class="container-page py-10 sm:py-14">
        @if (session('success'))
            <div class="mb-6 rounded-xl border border-success/20 bg-success/10 px-5 py-4 text-sm font-bold text-success">{{ session('success') }}</div>
        @endif
        {{ $slot }}
    </main>
    <footer class="container-page pb-10 text-center text-xs text-muted">Paperflow &middot; Alur editorial conference yang dapat ditelusuri</footer>
</body>
</html>
