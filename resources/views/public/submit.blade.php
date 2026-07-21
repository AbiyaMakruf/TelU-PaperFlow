<x-layouts.public :title="$conference->name">
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
        <form method="POST" action="{{ route('public.submission.store', $conference->slug) }}" enctype="multipart/form-data" class="card p-6 sm:p-8">
            @csrf
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-danger/20 bg-danger/8 p-4 text-sm text-danger">
                    <p class="font-black">Periksa kembali data berikut:</p>
                    <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <h2 class="text-lg font-black text-navy">Informasi paper</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="sm:col-span-2"><span class="form-label">Judul paper *</span><input class="form-input" name="title" value="{{ old('title') }}" required></label>
                <label><span class="form-label">Corresponding author *</span><input class="form-input" name="author_name" value="{{ old('author_name') }}" required></label>
                <label><span class="form-label">Email author *</span><input class="form-input" type="email" name="author_email" value="{{ old('author_email') }}" required></label>
                <label><span class="form-label">Nomor telepon</span><input class="form-input" name="author_phone" value="{{ old('author_phone') }}"></label>
            </div>

            @if (count($form->schema))
                <div class="my-8 border-t border-navy/10"></div>
                <h2 class="text-lg font-black text-navy">Informasi tambahan</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    @foreach ($form->schema as $field)
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
            @if($captchaQuestion)<label class="mb-5 block"><span class="form-label">Verifikasi keamanan: berapa {{ $captchaQuestion }}? *</span><input class="form-input" type="number" name="captcha_answer" required></label>@endif
            <label><span class="form-label">File editable paper *</span><input class="form-input py-3" type="file" name="paper_file" accept="{{ collect($conference->allowedFileExtensions())->map(fn($e)=>'.'.$e)->implode(',') }}" required><span class="mt-2 block text-xs text-muted">Format {{ strtoupper(implode(', ', $conference->allowedFileExtensions())) }}. Maksimal {{ $conference->maxFileSizeMb() }} MB.</span></label>
            <div class="mt-8 flex items-center justify-between gap-4 border-t border-navy/10 pt-6">
                <p class="text-xs text-muted">File disimpan privat dan hanya dapat diakses pihak berwenang.</p>
                <button class="btn text-white" style="background:var(--brand-primary)" type="submit">Kirim submission</button>
            </div>
        </form>
        @endunless
    </div>
</x-layouts.public>
