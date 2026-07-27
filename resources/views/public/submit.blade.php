<x-layouts.public :title="$conference->formTitle()">
    @if($turnstileEnabled)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
    <div class="mx-auto max-w-3xl space-y-5" style="--brand-primary:{{ $conference->brandPrimary() }};--brand-accent:{{ $conference->brandAccent() }}">
        <!-- Top Navigation Bar -->
        <div class="flex items-center justify-between px-1">
            <a href="{{ route('public.conference.show', $conference) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-navy transition">
                <span>&larr;</span> Back to {{ $conference->name }}
            </a>
            @if($conference->brandLogoUrl())
                <img class="h-7 max-w-36 object-contain" src="{{ $conference->brandLogoUrl() }}" alt="{{ $conference->name }}">
            @endif
        </div>

        @unless($storageReady)
            <div class="rounded-2xl border border-rose-200 bg-white p-6 shadow-sm">
                <h2 class="font-black text-navy text-lg">Submissions Temporarily Unavailable</h2>
                <p class="mt-2 text-sm text-slate-600">The conference administrator needs to configure file storage first.</p>
            </div>
        @else
        <form method="POST" action="{{ route('public.submission.store', $conference->slug) }}" enctype="multipart/form-data" class="space-y-5" x-data="{ coAuthors: @js(old('co_authors', [])) }" onsubmit="const fileInput = this.querySelector('input[type=file]'); const maxBytes = {{ ($conference->maxFileSizeMb() ?: 25) * 1024 * 1024 }}; if (fileInput && fileInput.files[0] && fileInput.files[0].size > maxBytes) { alert('Ukuran file ' + fileInput.files[0].name + ' (' + (fileInput.files[0].size / (1024*1024)).toFixed(1) + ' MB) melebihi batas maksimal {{ $conference->maxFileSizeMb() ?: 25 }}MB. Silakan pilih file yang lebih kecil.'); fileInput.value = ''; return false; } const btn = this.querySelector('button[type=submit]'); if (btn) { btn.disabled = true; btn.innerHTML = 'Processing...'; }">
            @csrf

            <!-- 1. Google Form Header Card -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                @if($conference->brandBannerUrl())
                    <div class="w-full bg-slate-100 overflow-hidden">
                        <img src="{{ $conference->brandBannerUrl() }}" class="h-44 sm:h-56 w-full object-cover" alt="Form Banner Header">
                    </div>
                @else
                    <div class="h-3 w-full" style="background: var(--brand-primary)"></div>
                @endif

                <div class="p-6 sm:p-8">
                    <h1 class="text-2xl sm:text-3xl font-black text-navy leading-tight">{{ $conference->formTitle() }}</h1>
                    <div class="mt-3 text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $conference->formDescription() }}</div>

                    @if ($errors->any())
                        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800">
                            <p class="font-bold">Please review the following input errors:</p>
                            <ul class="mt-1.5 list-disc pl-5 space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <div class="mt-6 border-t border-slate-100 pt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-emerald-500"></span>
                            <span class="font-semibold text-slate-700">Official Submission Portal</span>
                        </div>
                        <span class="text-rose-600 font-bold">* Indicates required question</span>
                    </div>
                </div>
            </div>

            <!-- 2. Paper & Author Contact Details Card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 shadow-sm space-y-5">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-base font-black text-navy">Paper & Author Contact Details</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Enter paper identification and primary contact information.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <label>
                        <span class="form-label">Paper ID <span class="text-rose-600">*</span></span>
                        <input class="form-input" name="paper_id" value="{{ old('paper_id') }}" placeholder="e.g. 1570123456" required>
                        <span class="mt-1 block text-xs text-slate-500">Enter the Paper ID assigned by the conference system.</span>
                    </label>
                    <label class="sm:col-span-2">
                        <span class="form-label">Paper Title <span class="text-rose-600">*</span></span>
                        <input class="form-input" name="title" value="{{ old('title') }}" required placeholder="Enter full manuscript title">
                    </label>
                    <label>
                        <span class="form-label">Author Name <span class="text-rose-600">*</span></span>
                        <input class="form-input" name="author_name" value="{{ old('author_name') }}" required placeholder="Full author name">
                    </label>
                    <label>
                        <span class="form-label">Author Email Address <span class="text-rose-600">*</span></span>
                        <input class="form-input" type="email" name="author_email" value="{{ old('author_email') }}" required placeholder="author@example.com">
                    </label>
                    <div class="sm:col-span-2">
                        <span class="form-label">Mobile / WhatsApp Phone Number <span class="text-rose-600">*</span></span>
                        <div class="grid grid-cols-1 sm:grid-cols-[140px_1fr] gap-2">
                            <select class="form-input px-2" name="author_phone_country_code" required>
                                @foreach($countryCodes as $code=>$label)
                                    <option value="{{ $code }}" @selected(old('author_phone_country_code','+62')===$code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input class="form-input" type="tel" name="author_phone" value="{{ old('author_phone') }}" placeholder="81234567890" required>
                        </div>
                    </div>

                    <div class="sm:col-span-2 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-xs text-sky-900 flex items-start gap-3">
                        <span class="text-base leading-none">ℹ️</span>
                        <div>
                            <strong class="block font-bold">Primary Communication Channel</strong>
                            <p class="mt-0.5 leading-relaxed">This email address and phone number will be the main contact channel used by the editorial team if any manuscript revisions or corrections are required.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Co-Authors Card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 shadow-sm space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-black text-navy">Co-Authors</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Add additional co-authors. If the primary author does not respond after several days, email notifications and revision requests will automatically be re-sent to co-authors.</p>
                    </div>
                    <button type="button" class="btn btn-secondary text-xs px-3.5 py-2 w-full sm:w-auto shrink-0" x-on:click="coAuthors.push({ name: '', email: '' })">+ Add Co-Author</button>
                </div>

                <div class="space-y-3">
                    <template x-for="(author, index) in coAuthors" :key="index">
                        <section class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-bold text-navy text-xs" x-text="`Co-Author ${index + 1}`"></h3>
                                <button type="button" class="text-xs font-bold text-rose-600 hover:underline" x-on:click="coAuthors.splice(index, 1)">Remove</button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label><span class="form-label text-xs">Full Name <span class="text-rose-600">*</span></span><input class="form-input text-xs" type="text" :name="`co_authors[${index}][name]`" x-model="author.name" required placeholder="Co-author name"></label>
                                <label><span class="form-label text-xs">Email Address</span><input class="form-input text-xs" type="email" :name="`co_authors[${index}][email]`" x-model="author.email" placeholder="email@example.com"></label>
                            </div>
                        </section>
                    </template>
                    <p x-show="coAuthors.length === 0" class="rounded-xl border border-dashed border-slate-200 p-4 text-xs text-slate-500 text-center">No co-authors added yet. The primary author above serves as the main contact.</p>
                </div>
            </div>

            <!-- 4. Additional Information Card -->
            @php($additionalFields = collect($form->schema)->reject(fn ($field) => in_array($field['key'], ['co_authors', 'affiliation', 'country'])))
            @if ($additionalFields->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 shadow-sm space-y-4">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-base font-black text-navy">Additional Information</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Custom instructions or notes for the conference editorial committee.</p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($additionalFields as $field)
                            <div @class(['sm:col-span-2' => in_array($field['type'], ['textarea', 'radio'])])>
                                <label class="form-label" for="field-{{ $field['key'] }}">{{ $field['label'] }} @if($field['required'] ?? false)<span class="text-rose-600">*</span>@endif</label>
                                @if ($field['type'] === 'textarea')
                                    <textarea class="form-input min-h-28 py-3" id="field-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" @required($field['required'] ?? false) placeholder="Enter any notes or special instructions for the editorial team...">{{ old('answers.'.$field['key']) }}</textarea>
                                @elseif ($field['type'] === 'select')
                                    <select class="form-input" id="field-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" @required($field['required'] ?? false)>
                                        <option value="">Select...</option>
                                        @foreach ($field['options'] ?? [] as $option)<option @selected(old('answers.'.$field['key']) === $option)>{{ $option }}</option>@endforeach
                                    </select>
                                @elseif ($field['type'] === 'radio')
                                    <div class="flex flex-wrap gap-4">
                                        @foreach ($field['options'] ?? [] as $option)<label class="check-row"><input type="radio" name="answers[{{ $field['key'] }}]" value="{{ $option }}" @checked(old('answers.'.$field['key']) === $option) @required($field['required'] ?? false)><span>{{ $option }}</span></label>@endforeach
                                    </div>
                                @elseif ($field['type'] === 'checkbox')
                                    <label class="check-row"><input type="checkbox" id="field-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" value="1" @checked(old('answers.'.$field['key'])) @required($field['required'] ?? false)><span>I Agree</span></label>
                                @else
                                    <input class="form-input" id="field-{{ $field['key'] }}" type="{{ $field['type'] }}" name="answers[{{ $field['key'] }}]" value="{{ old('answers.'.$field['key']) }}" @required($field['required'] ?? false)>
                                @endif
                                @if ($field['help'] ?? null)<p class="mt-1 text-xs text-slate-500">{{ $field['help'] }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 5. Manuscript Source File Upload Card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 shadow-sm space-y-5">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-base font-black text-navy">Manuscript Source File</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Upload your editable camera-ready manuscript source file.</p>
                </div>

                @if($turnstileEnabled)
                    <div class="space-y-1">
                        <span class="form-label">Security Verification <span class="text-rose-600">*</span></span>
                        <div class="cf-turnstile" data-sitekey="{{ config('paperflow.turnstile.site_key') }}" data-action="paper_submission" data-appearance="always" data-theme="light"></div>
                    </div>
                @endif

                <label class="block">
                    <span class="form-label">Editable Manuscript File <span class="text-rose-600">*</span></span>
                    <input class="form-input py-3" type="file" name="paper_file" accept=".docx,.zip" required onchange="const maxBytes = {{ ($conference->maxFileSizeMb() ?: 25) * 1024 * 1024 }}; if (this.files[0] && this.files[0].size > maxBytes) { alert('Ukuran file ' + this.files[0].name + ' (' + (this.files[0].size / (1024*1024)).toFixed(1) + ' MB) melebihi batas maksimal {{ $conference->maxFileSizeMb() ?: 25 }}MB.'); this.value = ''; }">
                    <span class="mt-2 block text-xs leading-relaxed text-slate-500">Upload an editable source file: <strong>.docx</strong> for Microsoft Word or a <strong>.zip</strong> archive containing LaTeX sources, images, and bibliography. Do not upload PDF only. Maximum {{ $conference->maxFileSizeMb() }} MB.</span>
                </label>

                <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-xs text-slate-500">Files are securely stored privately and accessible only by authorized editorial staff.</p>
                    <button class="btn px-8 py-3 text-sm font-bold text-white shadow-md hover:shadow-lg transition w-full sm:w-auto" style="background:var(--brand-primary)" type="submit">Submit Manuscript &rarr;</button>
                </div>
            </div>
        </form>
        @endunless
    </div>
</x-layouts.public>
