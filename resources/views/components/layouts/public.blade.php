@props(['title' => 'Paperflow'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} &middot; Paperflow</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-warm text-ink antialiased">
    <header class="sticky top-0 z-50 border-b border-white/10 bg-navy text-white shadow-md">
        <div class="container-page flex min-h-18 items-center justify-between gap-3 py-3 sm:min-h-20">
            <x-brand class="text-white" />
        </div>
    </header>
    <main class="container-page py-6 sm:py-10">
        @if (session('success'))
            <div class="mb-6 rounded-xl border border-success/20 bg-success/10 px-5 py-4 text-sm font-bold text-success">{{ session('success') }}</div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
