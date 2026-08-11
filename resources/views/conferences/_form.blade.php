@php($conference = $conference ?? null)
<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label class="form-label" for="name">Conference Name</label>
        <input class="form-input" id="name" name="name" value="{{ old('name', $conference?->name) }}" required placeholder="e.g. International Conference on Science and Technology">
    </div>
    <div>
        <label class="form-label" for="slug">Public URL Slug</label>
        <div class="flex items-center rounded-xl border border-navy/15 bg-white focus-within:border-orange focus-within:ring-4 focus-within:ring-orange/10">
            <span class="pl-4 text-sm text-muted">/</span>
            <input class="min-h-12 min-w-0 flex-1 bg-transparent px-2 text-sm outline-none" id="slug" name="slug" value="{{ old('slug', $conference?->slug) }}" required placeholder="icoseit-2026">
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
        <input class="form-input" id="timezone" name="timezone" value="{{ old('timezone', $conference?->timezone ?? 'Asia/Jakarta') }}" required>
    </div>
    <div>
        <label class="form-label" for="starts_at">Start Date</label>
        <input class="form-input" id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $conference?->starts_at?->format('Y-m-d\TH:i')) }}">
    </div>
    <div>
        <label class="form-label" for="ends_at">End Date</label>
        <input class="form-input" id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $conference?->ends_at?->format('Y-m-d\TH:i')) }}">
    </div>
    <div>
        <label class="form-label" for="submission_opens_at">Submission Opens At</label>
        <input class="form-input" id="submission_opens_at" type="datetime-local" name="submission_opens_at" value="{{ old('submission_opens_at', $conference?->submission_opens_at?->format('Y-m-d\TH:i')) }}">
    </div>
    <div>
        <label class="form-label" for="submission_closes_at">Submission Closes At</label>
        <input class="form-input" id="submission_closes_at" type="datetime-local" name="submission_closes_at" value="{{ old('submission_closes_at', $conference?->submission_closes_at?->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="md:col-span-2 border-t border-navy/10 pt-5" x-data="{ submissionMode: '{{ old('submission_mode', $conference?->submissionMode() ?? 'paperflow_native') }}' }">
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
                    <strong class="block text-navy">Google Form External Sync</strong>
                    <small class="text-muted block mt-0.5">Authors submit via Google Form; submissions sync to Paperflow in real-time.</small>
                </span>
            </label>
        </div>

        @if($conference)
            <!-- Google Form Setup Instructions & Custom Column Header Mapping Card -->
            <div x-show="submissionMode === 'google_form_external'" class="mt-6 rounded-2xl border border-orange/20 bg-orange/5 p-5 space-y-6">
                <div>
                    <h4 class="font-extrabold text-navy text-sm flex items-center gap-2">
                        <span>🔗</span> Google Form Real-Time Sync Setup Guide
                    </h4>
                    <p class="text-xs text-muted mt-1">Follow these steps to automatically sync submissions from your existing Google Form or Spreadsheet into Paperflow:</p>
                    <ol class="mt-3 list-decimal list-inside text-xs text-navy/80 space-y-1.5 font-medium">
                        <li>Open your Google Form or its connected Google Sheets spreadsheet.</li>
                        <li>Go to <strong>Extensions &gt; Apps Script</strong> (or Click <strong>&vellip; &gt; Script Editor</strong>).</li>
                        <li>Paste the Google Apps Script integration code into the editor.</li>
                        <li>Set the Webhook URL to: <code class="font-mono bg-white px-2 py-0.5 rounded border border-navy/10 text-orange font-bold">{{ url('/api/webhooks/google-form/'.$conference->slug) }}</code></li>
                        <li>Set the Secret Token header <code class="font-mono bg-white px-1.5 py-0.5 rounded border border-navy/10 text-navy">X-Paperflow-Secret</code> to: <code class="font-mono bg-white px-2 py-0.5 rounded border border-navy/10 text-navy font-bold">{{ env('GOOGLE_FORM_WEBHOOK_SECRET', 'paperflow_webhook_secret_key') }}</code></li>
                        <li>Add an <strong>On form submit</strong> trigger under Apps Script Triggers (&num;1 Event Source: <em>From form / From spreadsheet</em>).</li>
                    </ol>
                </div>

                @php($mapping = $conference->googleFormMapping())
                <div class="border-t border-orange/15 pt-5">
                    <h4 class="font-extrabold text-navy text-sm">Google Form / Spreadsheet Column Header Mapping</h4>
                    <p class="text-xs text-muted mt-0.5">Enter the exact question or column header text used in your Google Form so Paperflow knows how to read your entries.</p>
                    
                    <div class="grid gap-4 sm:grid-cols-2 mt-4 text-xs">
                        <div>
                            <label class="form-label text-xs">Paper ID Column Header</label>
                            <input class="form-input text-xs" name="google_form_mapping[paper_id_column]" value="{{ old('google_form_mapping.paper_id_column', $mapping['paper_id_column']) }}" placeholder="ID Papers (#)">
                        </div>
                        <div>
                            <label class="form-label text-xs">Paper Title Column Header</label>
                            <input class="form-input text-xs" name="google_form_mapping[title_column]" value="{{ old('google_form_mapping.title_column', $mapping['title_column']) }}" placeholder="Paper's Title">
                        </div>
                        <div>
                            <label class="form-label text-xs">Registered Author Name Column Header</label>
                            <input class="form-input text-xs" name="google_form_mapping[author_name_column]" value="{{ old('google_form_mapping.author_name_column', $mapping['author_name_column']) }}" placeholder="Registered Author's Name">
                        </div>
                        <div>
                            <label class="form-label text-xs">Registered Author Email Column Header</label>
                            <input class="form-input text-xs" name="google_form_mapping[author_email_column]" value="{{ old('google_form_mapping.author_email_column', $mapping['author_email_column']) }}" placeholder="Registered Author's Email Address">
                        </div>
                        <div>
                            <label class="form-label text-xs">Registered Author Phone Column Header</label>
                            <input class="form-input text-xs" name="google_form_mapping[author_phone_column]" value="{{ old('google_form_mapping.author_phone_column', $mapping['author_phone_column']) }}" placeholder="Registered Author's Phone Number">
                        </div>
                        <div>
                            <label class="form-label text-xs">Presenter Name Column Header</label>
                            <input class="form-input text-xs" name="google_form_mapping[presenter_name_column]" value="{{ old('google_form_mapping.presenter_name_column', $mapping['presenter_name_column']) }}" placeholder="Name of Presenter">
                        </div>
                        <div>
                            <label class="form-label text-xs">Manuscript Source Upload Column Header</label>
                            <input class="form-input text-xs" name="google_form_mapping[manuscript_file_column]" value="{{ old('google_form_mapping.manuscript_file_column', $mapping['manuscript_file_column']) }}" placeholder="Upload the Manuscript Source">
                        </div>
                        <div>
                            <label class="form-label text-xs">Revision Form Upload Column Header</label>
                            <input class="form-input text-xs" name="google_form_mapping[revision_form_column]" value="{{ old('google_form_mapping.revision_form_column', $mapping['revision_form_column']) }}" placeholder="Upload the Revision Form">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label text-xs">Similarity Report Upload Column Header</label>
                            <input class="form-input text-xs" name="google_form_mapping[similarity_report_column]" value="{{ old('google_form_mapping.similarity_report_column', $mapping['similarity_report_column']) }}" placeholder="Upload the Simmilarity Report">
                        </div>
                    </div>
                </div>
            </div>
        @endif
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
