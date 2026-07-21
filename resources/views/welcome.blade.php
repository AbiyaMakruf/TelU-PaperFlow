<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paperflow · Editorial workflow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-warm text-ink antialiased">
    <header class="border-b border-navy/10 bg-warm/90 backdrop-blur">
        <div class="container-page flex h-20 items-center justify-between">
            <x-brand class="text-navy" />
            <a href="{{ route('login') }}" class="btn btn-secondary">Login editorial</a>
        </div>
    </header>
    <main>
        <section class="container-page grid min-h-[650px] items-center gap-12 py-20 lg:grid-cols-[1.1fr_.9fr]">
            <div>
                <p class="text-sm font-black uppercase tracking-[.24em] text-orange">Conference editorial workflow</p>
                <h1 class="mt-5 max-w-3xl text-5xl font-black leading-[1.05] text-navy sm:text-6xl">Dari submission hingga siap ke EDAS.</h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-muted">Paperflow menyatukan form author, assignment PIC, pemeriksaan editorial, versioning dokumen, dan final review dalam satu workspace.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#conferences" class="btn btn-primary">Cari conference</a>
                    <a href="{{ route('login') }}" class="btn btn-ghost">Saya tim editorial →</a>
                </div>
            </div>
            <div class="relative">
                <div class="absolute inset-10 rounded-full bg-orange/20 blur-3xl"></div>
                <div class="relative rounded-[2rem] bg-navy p-7 text-white shadow-2xl shadow-navy/25">
                    <div class="flex items-center justify-between"><p class="font-bold">Editorial progress</p><span class="badge badge-warning">Live workspace</span></div>
                    <div class="mt-8 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-white/8 p-5"><p class="text-sm text-white/55">Paper aktif</p><p class="mt-2 text-3xl font-black">128</p></div>
                        <div class="rounded-2xl bg-orange p-5"><p class="text-sm text-white/75">Ready EDAS</p><p class="mt-2 text-3xl font-black">34</p></div>
                    </div>
                    <div class="mt-5 space-y-3">
                        @foreach ([['ICO-0214', 'Editorial review', '72%'], ['ICO-0215', 'Waiting author', '48%'], ['ICO-0216', 'Final reviewer', '91%']] as $item)
                            <div class="rounded-xl bg-white/8 p-4"><div class="flex justify-between text-sm"><span class="font-bold">{{ $item[0] }}</span><span class="text-white/60">{{ $item[1] }}</span></div><div class="mt-3 h-1.5 rounded-full bg-white/10"><div class="h-full rounded-full bg-orange" style="width: {{ $item[2] }}"></div></div></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
        <section id="conferences" class="border-t border-navy/10 bg-white py-20">
            <div class="container-page">
                <p class="text-sm font-black uppercase tracking-[.2em] text-orange">Open submission</p>
                <h2 class="mt-3 text-3xl font-black text-navy">Conference tersedia</h2>
                <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @forelse ($conferences as $conference)
                        <a href="/{{ $conference->slug }}" class="card group p-6 transition hover:-translate-y-1 hover:border-orange/40 hover:shadow-xl">
                            <div class="flex items-start justify-between"><span class="grid size-11 place-items-center rounded-xl bg-navy/8 font-black text-navy">{{ strtoupper(substr($conference->name, 0, 1)) }}</span><span class="text-orange transition group-hover:translate-x-1">→</span></div>
                            <h3 class="mt-5 text-lg font-extrabold text-navy">{{ $conference->name }}</h3>
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-muted">{{ $conference->description ?: 'Submission paper dan dokumen editable.' }}</p>
                        </a>
                    @empty
                        <div class="card col-span-full p-8 text-center text-muted">Belum ada conference yang membuka submission.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </main>
    <footer class="bg-navy py-8 text-white/60"><div class="container-page flex flex-wrap justify-between gap-3 text-sm"><span>© {{ date('Y') }} Paperflow</span><span>Editorial work, clearly moving.</span></div></footer>
</body>
</html>
