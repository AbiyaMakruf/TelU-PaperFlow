@props(['title' => 'Paperflow'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Paperflow' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-warm text-ink antialiased">
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_.95fr]">
        <section class="relative hidden overflow-hidden bg-navy p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -right-32 -top-32 size-96 rounded-full bg-orange/15 blur-3xl"></div>
            <x-brand class="relative text-white" />
            <div class="relative max-w-xl">
                <p class="mb-4 text-sm font-bold uppercase tracking-[.24em] text-orange">Editorial workspace</p>
                <h1 class="text-5xl font-black leading-tight">Setiap paper bergerak dengan jelas.</h1>
                <p class="mt-5 max-w-lg text-lg leading-8 text-white/70">Submission, pemeriksaan, revisi, dan persiapan EDAS dalam satu alur yang dapat ditelusuri.</p>
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
