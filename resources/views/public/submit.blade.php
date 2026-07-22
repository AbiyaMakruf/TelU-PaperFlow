<x-layouts.public :title="$conference->name">
    @if($turnstileEnabled)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
    <div class="mx-auto max-w-4xl" style="--brand-primary:{{ $conference->brandPrimary() }};--brand-accent:{{ $conference->brandAccent() }}">
        <div class="mb-8">
            @if($conference->brandLogoUrl())<img class="mb-5 max-h-20 max-w-56" src="{{ $conference->brandLogoUrl() }}" alt="Logo {{ $conference->name }}">@endif
            <a href="{{ route('public.conference.show', $conference) }}" class="back-link">&larr; Halaman conference</a>
            <p class="eyebrow">Paper submission</p>
            <h1 class="page-title" style="color:var(--brand-primary)">{{ $conference->name }}</h1>
            @if ($conference->description)<p class="page-subtitle">{{ $conference->description }}</p>@endif
        </div>

        @unless($storageReady)
            <div class="card border border-orange/30 p-6"><h2 class="font-black text-navy">Submission sementara belum tersedia</h2><p class="mt-2 text-sm text-muted">Admin conference perlu menyiapkan penyimpanan file terlebih dahulu.</p></div>
        @else
        <form method="POST" action="{{ route('public.submission.store', $conference->slug) }}" enctype="multipart/form-data" class="card p-6 sm:p-8" x-data="{ coAuthors: @js(old('co_authors', [])) }">
            @csrf
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-danger/20 bg-danger/8 p-4 text-sm text-danger">
                    <p class="font-black">Periksa kembali data berikut:</p>
                    <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <h2 class="text-lg font-black text-navy">Informasi paper</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label><span class="form-label">Paper ID *</span><input class="form-input" name="paper_id" value="{{ old('paper_id') }}" placeholder="Contoh: 1570123456" required><span class="mt-1.5 block text-xs text-muted">Masukkan ID paper dari sistem conference.</span></label>
                <label class="sm:col-span-2"><span class="form-label">Judul paper *</span><input class="form-input" name="title" value="{{ old('title') }}" required></label>
                <label><span class="form-label">Corresponding author *</span><input class="form-input" name="author_name" value="{{ old('author_name') }}" required></label>
                <label><span class="form-label">Email author *</span><input class="form-input" type="email" name="author_email" value="{{ old('author_email') }}" required></label>
                <div><span class="form-label">Nomor handphone / WhatsApp *</span><div class="grid grid-cols-[minmax(0,1.25fr)_minmax(0,1.75fr)] gap-2"><select class="form-input px-2" name="author_phone_country_code" required>@foreach($countryCodes as $code=>$label)<option value="{{ $code }}" @selected(old('author_phone_country_code','+62')===$code)>{{ $label }}</option>@endforeach</select><input class="form-input" type="tel" name="author_phone" value="{{ old('author_phone') }}" placeholder="81234567890" required></div></div>
            </div>

            <div class="my-8 border-t border-navy/10"></div>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-black text-navy">Co-author</h2><p class="mt-1 text-sm text-muted">Tambahkan satu section untuk setiap co-author.</p></div><button type="button" class="btn btn-secondary w-full sm:w-auto" x-on:click="coAuthors.push({ name: '', email: '', affiliation: '' })">+ Add co-author</button></div>
            <div class="mt-5 space-y-4">
                <template x-for="(author, index) in coAuthors" :key="index">
                    <section class="rounded-2xl border border-navy/10 bg-warm/60 p-5">
                        <div class="flex items-center justify-between"><h3 class="font-black text-navy" x-text="`Co-author ${index + 1}`"></h3><button type="button" class="text-sm font-bold text-danger" x-on:click="coAuthors.splice(index, 1)">Hapus</button></div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <label><span class="form-label">Nama *</span><input class="form-input" type="text" :name="`co_authors[${index}][name]`" x-model="author.name" required></label>
                            <label><span class="form-label">Email</span><input class="form-input" type="email" :name="`co_authors[${index}][email]`" x-model="author.email"></label>
                            <label><span class="form-label">Afiliasi</span><input class="form-input" type="text" :name="`co_authors[${index}][affiliation]`" x-model="author.affiliation"></label>
                        </div>
                    </section>
                </template>
                <p x-show="coAuthors.length === 0" class="rounded-xl border border-dashed border-navy/15 p-4 text-sm text-muted">Belum ada co-author. Corresponding author di atas menjadi author pertama.</p>
            </div>

            @php($additionalFields = collect($form->schema)->reject(fn ($field) => $field['key'] === 'co_authors'))
            @if ($additionalFields->isNotEmpty())
                <div class="my-8 border-t border-navy/10"></div>
                <h2 class="text-lg font-black text-navy">Informasi tambahan</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    @foreach ($additionalFields as $field)
                        <div @class(['sm:col-span-2' => in_array($field['type'], ['textarea', 'radio'])])>
                            <label class="form-label" for="field-{{ $field['key'] }}">{{ $field['label'] }} @if($field['required'] ?? false)*@endif</label>
                            @if ($field['type'] === 'textarea')
                                <textarea class="form-input min-h-28 py-3" id="field-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" @required($field['required'] ?? false)>{{ old('answers.'.$field['key']) }}</textarea>
                            @elseif ($field['type'] === 'select')
                                <select class="form-input" id="field-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" @required($field['required'] ?? false)>
                                    <option value="">Pilih...</option>
                                    @foreach ($field['options'] ?? [] as $option)<option @selected(old('answers.'.$field['key']) === $option)>{{ $option }}</option>@endforeach
                                </select>
                            @elseif ($field['type'] === 'radio')
                                <div class="flex flex-wrap gap-4">
                                    @foreach ($field['options'] ?? [] as $option)<label class="check-row"><input type="radio" name="answers[{{ $field['key'] }}]" value="{{ $option }}" @checked(old('answers.'.$field['key']) === $option) @required($field['required'] ?? false)><span>{{ $option }}</span></label>@endforeach
                                </div>
                            @elseif ($field['type'] === 'checkbox')
                                <label class="check-row"><input type="checkbox" id="field-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" value="1" @checked(old('answers.'.$field['key'])) @required($field['required'] ?? false)><span>Saya menyetujui</span></label>
                            @else
                                <input class="form-input" id="field-{{ $field['key'] }}" type="{{ $field['type'] }}" name="answers[{{ $field['key'] }}]" value="{{ old('answers.'.$field['key']) }}" @required($field['required'] ?? false)>
                            @endif
                            @if ($field['help'] ?? null)<p class="mt-1.5 text-xs text-muted">{{ $field['help'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="my-8 border-t border-navy/10"></div>
            @if($turnstileEnabled)<div class="mb-5"><span class="form-label">Verifikasi keamanan *</span><div class="cf-turnstile" data-sitekey="{{ config('paperflow.turnstile.site_key') }}" data-action="paper_submission" data-appearance="always" data-theme="light"></div></div>@endif
            <label><span class="form-label">File editable paper *</span><input class="form-input py-3" type="file" name="paper_file" accept=".docx,.zip" required><span class="mt-2 block text-xs leading-5 text-muted">Upload file yang masih dapat diedit: <strong>.docx</strong> untuk Microsoft Word atau <strong>.zip</strong> yang berisi seluruh source LaTeX beserta gambar/bibliography. Jangan upload PDF saja. Maksimal {{ $conference->maxFileSizeMb() }} MB.</span></label>
            <div class="mt-8 flex flex-col gap-4 border-t border-navy/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-muted">File disimpan privat dan hanya dapat diakses pihak berwenang.</p>
                <button class="btn w-full text-white sm:w-auto" style="background:var(--brand-primary)" type="submit">Kirim submission</button>
            </div>
        </form>
        @endunless
    </div>
</x-layouts.public>
