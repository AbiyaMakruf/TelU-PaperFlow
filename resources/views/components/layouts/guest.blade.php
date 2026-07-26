@props(['title' => 'Paperflow'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Paperflow' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-warm text-ink antialiased">
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_.95fr]">
        <section class="relative hidden overflow-hidden bg-navy p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -right-32 -top-32 size-96 rounded-full bg-orange/15 blur-3xl"></div>
            <x-brand class="relative text-white" />
            <div class="relative max-w-xl">
                <p class="mb-4 text-sm font-bold uppercase tracking-[.24em] text-orange">Editorial workspace</p>
                <h1 class="text-5xl font-black leading-tight">Every paper moves with clarity.</h1>
                <p class="mt-5 max-w-lg text-lg leading-8 text-white/70">Submissions, compliance checks, revisions, and EDAS handoffs in one transparent workflow.</p>
            </div>
            <p class="relative text-sm text-white/50">Paperflow · Conference editorial management</p>
        </section>
        <main class="flex items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-md">
                <x-brand class="mb-10 text-navy lg:hidden" />
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
