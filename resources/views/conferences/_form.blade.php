@php
    $conference = $conference ?? null;
    $mapping = $conference?->googleFormMapping() ?? [
        'paper_id_column' => 'ID Papers (#)',
        'title_column' => "Paper's Title",
        'author_name_column' => "Registered Author's Name",
        'author_email_column' => "Registered Author's Email Address",
        'author_phone_column' => "Registered Author's Phone Number",
        'manuscript_file_column' => 'Upload the Manuscript Source',
        'custom_fields' => [
            ['label' => 'Presenter Name', 'column' => 'Name of Presenter'],
            ['label' => 'Revision Form Link', 'column' => 'Upload the Revision Form'],
            ['label' => 'Similarity Report Link', 'column' => 'Upload the Simmilarity Report'],
        ],
    ];
@endphp

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('conferenceForm', (initialSlug, initialMode, initialCustomFields) => ({
        slug: initialSlug || '',
        submissionMode: initialMode || 'paperflow_native',
        customFields: initialCustomFields || []
    }));
});
</script>

<div class="grid gap-5 md:grid-cols-2" x-data="conferenceForm('{{ old('slug', $conference?->slug ?? '') }}', '{{ old('submission_mode', $conference?->submissionMode() ?? 'paperflow_native') }}', {{ json_encode(old('google_form_mapping.custom_fields', $mapping['custom_fields'] ?? [])) }})">
    <div class="md:col-span-2">
        <label class="form-label" for="name">Conference Name</label>
        <input class="form-input" id="name" name="name" value="{{ old('name', $conference?->name) }}" required placeholder="e.g. International Conference on Science and Technology">
    </div>
    <div>
        <label class="form-label" for="slug">Public URL Slug</label>
        <div class="flex items-center rounded-xl border border-navy/15 bg-white focus-within:border-orange focus-within:ring-4 focus-within:ring-orange/10">
            <span class="pl-4 text-sm text-muted">/</span>
            <input class="min-h-12 min-w-0 flex-1 bg-transparent px-2 text-sm outline-none" id="slug" name="slug" x-model="slug" value="{{ old('slug', $conference?->slug) }}" required placeholder="icoseit-2026">
        </div>
    </div>
    <div>
        <label class="form-label" for="status">Status</label>
        <select class="form-input" id="status" name="status">
            @foreach (\App\Enums\ConferenceStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(old('status', $conference?->status?->value ?? 'draft') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-input min-h-28 py-3" id="description" name="description" placeholder="Brief conference overview...">{{ old('description', $conference?->description) }}</textarea>
    </div>
    <div>
        <label class="form-label" for="timezone">Timezone</label>
        <select class="form-input" id="timezone" name="timezone" required>
            @php
                $timezones = [
                    'Asia/Jakarta' => 'Asia/Jakarta (GMT+7 - WIB)',
                    'Asia/Makassar' => 'Asia/Makassar (GMT+8 - WITA)',
                    'Asia/Jayapura' => 'Asia/Jayapura (GMT+9 - WIT)',
                    'Asia/Singapore' => 'Asia/Singapore (GMT+8)',
                    'Asia/Bangkok' => 'Asia/Bangkok (GMT+7)',
                    'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur (GMT+8)',
                    'Asia/Tokyo' => 'Asia/Tokyo (GMT+9)',
                    'Asia/Riyadh' => 'Asia/Riyadh (GMT+3)',
                    'Asia/Dubai' => 'Asia/Dubai (GMT+4)',
                    'UTC' => 'UTC (GMT+0)',
                    'Europe/London' => 'Europe/London (GMT+0/+1)',
                    'Europe/Paris' => 'Europe/Paris (GMT+1/+2)',
                    'Europe/Berlin' => 'Europe/Berlin (GMT+1/+2)',
                    'America/New_York' => 'America/New_York (GMT-5/-4)',
                    'America/Chicago' => 'America/Chicago (GMT-6/-5)',
                    'America/Denver' => 'America/Denver (GMT-7/-6)',
                    'America/Los_Angeles' => 'America/Los_Angeles (GMT-8/-7)',
                    'Australia/Sydney' => 'Australia/Sydney (GMT+10/+11)',
                ];
                $currentTimezone = old('timezone', $conference?->timezone ?? 'Asia/Jakarta');
                if (!isset($timezones[$currentTimezone])) {
                    $timezones[$currentTimezone] = $currentTimezone;
                }
            @endphp
            @foreach($timezones as $tzValue => $tzLabel)
                <option value="{{ $tzValue }}" @selected($currentTimezone === $tzValue)>{{ $tzLabel }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label" for="starts_at">Start Date</label>
        <input class="form-input" id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $conference?->starts_at?->format('Y-m-d\TH:i')) }}">
    </div>
    <div>
        <label class="form-label" for="ends_at">End Date</label>
        <input class="form-input" id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $conference?->ends_at?->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="md:col-span-2 border-t border-navy/10 pt-5">
        <h3 class="font-black text-navy">Submission Workflow Mode</h3>
        <p class="text-xs text-muted mt-1">Select how authors submit their papers for this conference.</p>
        <div class="grid gap-3 sm:grid-cols-2 mt-3">
            <label class="check-row rounded-xl border border-navy/10 p-4 cursor-pointer" :class="submissionMode === 'paperflow_native' ? 'border-orange bg-orange/5' : ''">
                <input type="radio" name="submission_mode" value="paperflow_native" x-model="submissionMode" @checked(old('submission_mode', $conference?->submissionMode() ?? 'paperflow_native') === 'paperflow_native')>
                <span>
                    <strong class="block text-navy">Paperflow Native Form (Default)</strong>
                    <small class="text-muted block mt-0.5">Authors submit directly via the conference's public Paperflow webpage.</small>
                </span>
            </label>
            <label class="check-row rounded-xl border border-navy/10 p-4 cursor-pointer" :class="submissionMode === 'google_form_external' ? 'border-orange bg-orange/5' : ''">
                <input type="radio" name="submission_mode" value="google_form_external" x-model="submissionMode" @checked(old('submission_mode', $conference?->submissionMode()) === 'google_form_external')>
                <span>
                    <strong class="block text-navy">Google Form / Spreadsheet CSV Import Mode</strong>
                    <small class="text-muted block mt-0.5">Authors submit via Google Form; staff import CSV exports anytime with auto-detection.</small>
                </span>
            </label>
        </div>

        <!-- Google Form / Spreadsheet CSV Upload Section -->
        <div x-show="submissionMode === 'google_form_external'" class="mt-6 rounded-2xl border border-orange/20 bg-orange/5 p-5 space-y-4">
            <div>
                <h4 class="font-extrabold text-navy text-sm flex items-center gap-2">
                    <span>📥</span> Smart CSV / Excel Import Mode Active
                </h4>
                <p class="text-xs text-navy/80 mt-1 leading-relaxed">
                    Paperflow will automatically detect header columns from your Google Form or Google Sheets CSV export file.
                </p>
            </div>

            <div class="border-t border-orange/15 pt-4 space-y-3">
                <label class="form-label text-xs font-bold text-navy">
                    📄 Initial Submissions CSV / Excel File <span class="text-muted font-normal">(Optional — Can be skipped)</span>
                </label>
                <input type="file" name="initial_csv_file" accept=".csv,.tsv,.txt" class="form-input text-xs py-2.5 bg-white">
                <p class="text-xs text-muted leading-relaxed">
                    You can attach a Google Form CSV export file right now to immediately import initial papers upon creation. Or leave this blank to skip and create the conference first.
                </p>
            </div>
        </div>
    </div>

    @if($conference)
        <div class="md:col-span-2 border-t border-navy/10 pt-5">
            <h3 class="font-black text-navy">File Upload Rules</h3>
        </div>
        <div>
            <label class="form-label">Max File Size (MB)</label>
            <input class="form-input" type="number" min="1" max="100" name="max_file_mb" value="{{ old('max_file_mb',$conference->maxFileSizeMb()) }}">
        </div>
        <div>
            <span class="form-label">Allowed Extensions</span>
            <div class="flex flex-wrap gap-2">
                @foreach(['doc','docx','tex','zip','pdf'] as $ext)
                    <label class="check-row p-3">
                        <input type="checkbox" name="allowed_extensions[]" value="{{ $ext }}" @checked(in_array($ext,old('allowed_extensions',$conference->settings['allowed_extensions'] ?? ['doc','docx','tex','zip'])))>
                        <span>{{ strtoupper($ext) }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="md:col-span-2 border-t border-navy/10 pt-5">
            <h3 class="font-black text-navy">Public Branding & Google Form Design</h3>
            <p class="text-xs text-muted mt-1">Customize your submission page banner image, form header title, description, and colors.</p>
        </div>
        <div>
            <label class="form-label">Primary Color</label>
            <input class="form-input h-12" type="color" name="brand_primary" value="{{ old('brand_primary',$conference->brandPrimary()) }}">
        </div>
        <div>
            <label class="form-label">Accent Color</label>
            <input class="form-input h-12" type="color" name="brand_accent" value="{{ old('brand_accent',$conference->brandAccent()) }}">
        </div>
        <div>
            <label class="form-label">Tagline</label>
            <input class="form-input" name="brand_tagline" value="{{ old('brand_tagline',$conference->settings['brand_tagline'] ?? '') }}" placeholder="e.g. Advancing Technology for Humanity">
        </div>
        <div>
            <label class="form-label">Logo Image (max 2 MB)</label>
            <input class="form-input py-3" type="file" name="brand_logo" accept="image/*">
            @if($conference->brandLogoUrl())<img src="{{ $conference->brandLogoUrl() }}" class="mt-2 h-10 object-contain rounded border border-slate-200 p-1">@endif
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Header Banner Image (Google Form Top Banner - Max 4 MB)</label>
            <input class="form-input py-3" type="file" name="brand_banner" accept="image/*">
            <span class="mt-1 block text-xs text-muted">Upload a banner image displayed at the very top of the submission form (Recommended aspect ratio 4:1 or 1200x300px).</span>
            @if($conference->brandBannerUrl())
                <div class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                    <img src="{{ $conference->brandBannerUrl() }}" class="h-28 w-full object-cover">
                </div>
            @endif
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Submission Form Header Title</label>
            <input class="form-input" name="form_title" value="{{ old('form_title', $conference->settings['form_title'] ?? '') }}" placeholder="{{ $conference->formTitle() }}">
            <span class="mt-1 block text-xs text-muted">Title displayed in the top card of the submission form.</span>
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Submission Form Description & Instructions</label>
            <textarea class="form-input min-h-24 py-3" name="form_description" placeholder="{{ $conference->formDescription() }}">{{ old('form_description', $conference->settings['form_description'] ?? '') }}</textarea>
            <span class="mt-1 block text-xs text-muted">Detailed description or instructions displayed before the input fields.</span>
        </div>
    @endif
</div>
