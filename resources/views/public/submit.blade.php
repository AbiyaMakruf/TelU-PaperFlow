<x-layouts.public :title="$conference->name">
    @if($turnstileEnabled)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
    <div class="mx-auto max-w-4xl" style="--brand-primary:{{ $conference->brandPrimary() }};--brand-accent:{{ $conference->brandAccent() }}">
        <div class="mb-8">
            @if($conference->brandLogoUrl())<img class="mb-5 max-h-20 max-w-56 object-contain" src="{{ $conference->brandLogoUrl() }}" alt="Logo {{ $conference->name }}">@endif
            <a href="{{ route('public.conference.show', $conference) }}" class="back-link">&larr; Conference Page</a>
            <p class="eyebrow">Paper Submission</p>
            <h1 class="page-title" style="color:var(--brand-primary)">{{ $conference->name }}</h1>
            @if ($conference->description)<p class="page-subtitle">{{ $conference->description }}</p>@endif
        </div>

        @unless($storageReady)
            <div class="card border border-orange/30 p-6">
                <h2 class="font-black text-navy">Submissions Temporarily Unavailable</h2>
                <p class="mt-2 text-sm text-muted">The conference administrator needs to configure file storage first.</p>
            </div>
        @else
        <form method="POST" action="{{ route('public.submission.store', $conference->slug) }}" enctype="multipart/form-data" class="card p-6 sm:p-8" x-data="{ coAuthors: @js(old('co_authors', [])) }">
            @csrf
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-danger/20 bg-danger/8 p-4 text-sm text-danger">
                    <p class="font-black">Please review the following input errors:</p>
                    <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <h2 class="text-lg font-black text-navy">Paper Details</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label>
                    <span class="form-label">Paper ID *</span>
                    <input class="form-input" name="paper_id" value="{{ old('paper_id') }}" placeholder="e.g. 1570123456" required>
                    <span class="mt-1.5 block text-xs text-muted">Enter the Paper ID assigned by the conference system.</span>
                </label>
                <label class="sm:col-span-2">
                    <span class="form-label">Paper Title *</span>
                    <input class="form-input" name="title" value="{{ old('title') }}" required placeholder="Enter full paper title">
                </label>
                <label>
                    <span class="form-label">Corresponding Author Name *</span>
                    <input class="form-input" name="author_name" value="{{ old('author_name') }}" required placeholder="Full author name">
                </label>
                <label>
                    <span class="form-label">Corresponding Author Email *</span>
                    <input class="form-input" type="email" name="author_email" value="{{ old('author_email') }}" required placeholder="author@example.com">
                </label>
                <div>
                    <span class="form-label">Mobile / WhatsApp Phone Number *</span>
                    <div class="grid grid-cols-1 sm:grid-cols-[140px_1fr] gap-2">
                        <select class="form-input px-2" name="author_phone_country_code" required>
                            @foreach($countryCodes as $code=>$label)
                                <option value="{{ $code }}" @selected(old('author_phone_country_code','+62')===$code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input class="form-input" type="tel" name="author_phone" value="{{ old('author_phone') }}" placeholder="81234567890" required>
                    </div>
                </div>
            </div>

            <div class="my-8 border-t border-navy/10"></div>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-black text-navy">Co-Authors</h2>
                    <p class="mt-1 text-sm text-muted">Add a section for each additional co-author.</p>
                </div>
                <button type="button" class="btn btn-secondary w-full sm:w-auto" x-on:click="coAuthors.push({ name: '', email: '', affiliation: '' })">+ Add Co-Author</button>
            </div>
            <div class="mt-5 space-y-4">
                <template x-for="(author, index) in coAuthors" :key="index">
                    <section class="rounded-2xl border border-navy/10 bg-warm/60 p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="font-black text-navy" x-text="`Co-Author ${index + 1}`"></h3>
                            <button type="button" class="text-sm font-bold text-danger" x-on:click="coAuthors.splice(index, 1)">Remove</button>
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <label><span class="form-label">Full Name *</span><input class="form-input" type="text" :name="`co_authors[${index}][name]`" x-model="author.name" required placeholder="Co-author name"></label>
                            <label><span class="form-label">Email Address</span><input class="form-input" type="email" :name="`co_authors[${index}][email]`" x-model="author.email" placeholder="email@example.com"></label>
                            <label><span class="form-label">Affiliation / Institution</span><input class="form-input" type="text" :name="`co_authors[${index}][affiliation]`" x-model="author.affiliation" placeholder="University / Organization"></label>
                        </div>
                    </section>
                </template>
                <p x-show="coAuthors.length === 0" class="rounded-xl border border-dashed border-navy/15 p-4 text-sm text-muted">No co-authors added yet. The corresponding author above serves as the primary author.</p>
            </div>

            @php($additionalFields = collect($form->schema)->reject(fn ($field) => $field['key'] === 'co_authors'))
            @if ($additionalFields->isNotEmpty())
                <div class="my-8 border-t border-navy/10"></div>
                <h2 class="text-lg font-black text-navy">Additional Information</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    @foreach ($additionalFields as $field)
                        <div @class(['sm:col-span-2' => in_array($field['type'], ['textarea', 'radio'])])>
                            <label class="form-label" for="field-{{ $field['key'] }}">{{ $field['label'] }} @if($field['required'] ?? false)*@endif</label>
                            @if ($field['type'] === 'textarea')
                                <textarea class="form-input min-h-28 py-3" id="field-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" @required($field['required'] ?? false)>{{ old('answers.'.$field['key']) }}</textarea>
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
                            @if ($field['help'] ?? null)<p class="mt-1.5 text-xs text-muted">{{ $field['help'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="my-8 border-t border-navy/10"></div>
            @if($turnstileEnabled)
                <div class="mb-5">
                    <span class="form-label">Security Verification *</span>
                    <div class="cf-turnstile" data-sitekey="{{ config('paperflow.turnstile.site_key') }}" data-action="paper_submission" data-appearance="always" data-theme="light"></div>
                </div>
            @endif
            <label>
                <span class="form-label">Editable Manuscript File *</span>
                <input class="form-input py-3" type="file" name="paper_file" accept=".docx,.zip" required>
                <span class="mt-2 block text-xs leading-5 text-muted">Upload an editable source file: <strong>.docx</strong> for Microsoft Word or a <strong>.zip</strong> archive containing LaTeX sources, images, and bibliography. Do not upload PDF only. Maximum {{ $conference->maxFileSizeMb() }} MB.</span>
            </label>
            <div class="mt-8 flex flex-col gap-4 border-t border-navy/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-muted">Files are securely stored privately and accessible only by authorized editorial staff.</p>
                <button class="btn w-full text-white sm:w-auto font-bold" style="background:var(--brand-primary)" type="submit">Submit Manuscript &rarr;</button>
            </div>
        </form>
        @endunless
    </div>
</x-layouts.public>
