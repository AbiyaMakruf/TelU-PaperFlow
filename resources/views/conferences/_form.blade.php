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
    Alpine.data('conferenceForm', (initialSlug, initialMode, initialCustomFields, secretToken) => ({
        slug: initialSlug || '',
        submissionMode: initialMode || 'paperflow_native',
        baseUrl: window.location.origin,
        copiedCode: false,
        customFields: initialCustomFields || [],
        getAppsScriptCode() {
            const webhookUrl = this.baseUrl + '/api/webhooks/google-form/' + (this.slug && this.slug.trim() ? this.slug.trim() : '{your-slug}');
            return `/**\n * PAPERFLOW REAL-TIME GOOGLE FORM WEBHOOK INTEGRATION\n */\nconst PAPERFLOW_WEBHOOK_URL = "${webhookUrl}";\nconst SECRET_TOKEN = "${secretToken}";\n\nfunction onFormSubmit(e) {\n  try {\n    let payload = {};\n    if (e && e.namedValues) {\n      for (let key in e.namedValues) {\n        payload[key.trim()] = e.namedValues[key][0] || "";\n      }\n    } else if (e && e.response) {\n      let itemResponses = e.response.getItemResponses();\n      for (let i = 0; i < itemResponses.length; i++) {\n        let itemResponse = itemResponses[i];\n        let title = itemResponse.getItem().getTitle().trim();\n        let response = itemResponse.getResponse();\n        payload[title] = Array.isArray(response) ? response.join(", ") : response;\n      }\n    } else {\n      return;\n    }\n\n    let options = {\n      "method": "post",\n      "contentType": "application/json",\n      "headers": {\n        "X-Paperflow-Secret": SECRET_TOKEN\n      },\n      "payload": JSON.stringify(payload),\n      "muteHttpExceptions": true\n    };\n\n    UrlFetchApp.fetch(PAPERFLOW_WEBHOOK_URL, options);\n  } catch (error) {\n    Logger.log("Paperflow Webhook Error: " + error.toString());\n  }\n}`;
        }
    }));
});
</script>

<div class="grid gap-5 md:grid-cols-2" x-data="conferenceForm('{{ old('slug', $conference?->slug ?? '') }}', '{{ old('submission_mode', $conference?->submissionMode() ?? 'paperflow_native') }}', {{ json_encode(old('google_form_mapping.custom_fields', $mapping['custom_fields'] ?? [])) }}, '{{ env('GOOGLE_FORM_WEBHOOK_SECRET', 'paperflow_webhook_secret_key') }}')">
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
                    <strong class="block text-navy">Google Form External Sync</strong>
                    <small class="text-muted block mt-0.5">Authors submit via Google Form; submissions sync to Paperflow in real-time.</small>
                </span>
            </label>
        </div>

        <!-- Google Form Setup Instructions & Custom Column Header Mapping Card -->
        <div x-show="submissionMode === 'google_form_external'" class="mt-6 rounded-2xl border border-orange/20 bg-orange/5 p-5 space-y-6">
            <div>
                <h4 class="font-extrabold text-navy text-sm flex items-center gap-2">
                    <span>🔗</span> Google Form Real-Time Sync Setup Guide
                </h4>
                <p class="text-xs text-muted mt-1">Follow these exact step-by-step instructions to connect your Google Form / Spreadsheet to Paperflow:</p>
                <ol class="mt-3 list-decimal list-inside text-xs text-navy/90 space-y-3 font-medium">
                    <li>Open your Google Form (or its connected Google Sheets spreadsheet).</li>
                    <li>Click <strong>Extensions &gt; Apps Script</strong> (or click <strong>&vellip; &gt; Script Editor</strong>) to open the <code class="font-mono text-purple-950 bg-purple-100 px-1.5 py-0.5 rounded">script.google.com</code> editor.</li>
                    <li>
                        Clear any default code in <code class="font-mono bg-white px-1.5 py-0.5 rounded border border-navy/10">Code.gs</code>, then click <strong>"📋 Copy Complete Apps Script Code"</strong> below and paste it into the editor:
                        <div class="mt-2.5 bg-navy text-slate-100 p-4 rounded-xl font-mono text-[11px] leading-relaxed relative overflow-x-auto border border-navy/20 shadow-xs">
                            <div class="flex items-center justify-between gap-2 mb-2 pb-2 border-b border-white/10 flex-wrap">
                                <span class="text-xs text-orange font-bold font-sans">Google Apps Script Code (Code.gs)</span>
                                <button type="button" @click="navigator.clipboard.writeText(getAppsScriptCode()); copiedCode = true; setTimeout(() => copiedCode = false, 2000)" class="btn text-xs py-1 px-3 bg-orange hover:bg-orange-dark text-white font-sans font-bold rounded-lg transition shrink-0 shadow-2xs">
                                    <span x-text="copiedCode ? 'Copied to Clipboard! ✓' : '📋 Copy Complete Apps Script Code'">📋 Copy Complete Apps Script Code</span>
                                </button>
                            </div>
                            <pre class="whitespace-pre overflow-x-auto text-[11px] text-emerald-300" x-text="getAppsScriptCode()"></pre>
                        </div>
                    </li>
                    <li>Click <strong>Save 💾</strong> (or press <kbd class="px-1.5 py-0.5 bg-white border border-navy/20 rounded text-[10px]">Ctrl+S</kbd> / <kbd class="px-1.5 py-0.5 bg-white border border-navy/20 rounded text-[10px]">Cmd+S</kbd>).</li>
                    <li>
                        On the left sidebar of <code class="font-mono text-purple-950 bg-purple-100 px-1.5 py-0.5 rounded">script.google.com</code>, click <strong>Triggers ⏰</strong> (alarm clock icon) &rarr; Click <strong>+ Add Trigger</strong> (at bottom right):
                        <ul class="mt-1.5 ml-5 list-disc space-y-1 text-xs text-navy/80 font-normal">
                            <li>Choose function to run: <strong><code class="font-mono text-navy font-bold">onFormSubmit</code></strong></li>
                            <li>Select event source: <strong><code class="font-mono text-navy font-bold">From form</code></strong> (or <em>From spreadsheet</em>)</li>
                            <li>Select event type: <strong><code class="font-mono text-navy font-bold">On form submit</code></strong></li>
                        </ul>
                    </li>
                    <li>Click <strong>Save</strong> and grant Google permissions if prompted. Submissions will now sync instantly into Paperflow!</li>
                </ol>
            </div>

            <div class="border-t border-orange/15 pt-5 space-y-6">
                <div>
                    <h4 class="font-extrabold text-navy text-sm flex items-center gap-2">
                        <span>📌</span> Mandatory Column Header Mapping <span class="text-rose-600 font-bold text-xs">* Required</span>
                    </h4>
                    <p class="text-xs text-muted mt-0.5">These 6 core fields are required so Paperflow can properly index papers and author details.</p>
                    
                    <div class="grid gap-4 sm:grid-cols-2 mt-4 text-xs">
                        <div>
                            <label class="form-label text-xs font-bold">Paper ID Column Header <span class="text-rose-500">*</span></label>
                            <input class="form-input text-xs" name="google_form_mapping[paper_id_column]" value="{{ old('google_form_mapping.paper_id_column', $mapping['paper_id_column']) }}" required placeholder="ID Papers (#)">
                        </div>
                        <div>
                            <label class="form-label text-xs font-bold">Paper Title Column Header <span class="text-rose-500">*</span></label>
                            <input class="form-input text-xs" name="google_form_mapping[title_column]" value="{{ old('google_form_mapping.title_column', $mapping['title_column']) }}" required placeholder="Paper's Title">
                        </div>
                        <div>
                            <label class="form-label text-xs font-bold">Registered Author Name Column Header <span class="text-rose-500">*</span></label>
                            <input class="form-input text-xs" name="google_form_mapping[author_name_column]" value="{{ old('google_form_mapping.author_name_column', $mapping['author_name_column']) }}" required placeholder="Registered Author's Name">
                        </div>
                        <div>
                            <label class="form-label text-xs font-bold">Registered Author Email Column Header <span class="text-rose-500">*</span></label>
                            <input class="form-input text-xs" name="google_form_mapping[author_email_column]" value="{{ old('google_form_mapping.author_email_column', $mapping['author_email_column']) }}" required placeholder="Registered Author's Email Address">
                        </div>
                        <div>
                            <label class="form-label text-xs font-bold">Registered Author Phone Column Header <span class="text-rose-500">*</span></label>
                            <input class="form-input text-xs" name="google_form_mapping[author_phone_column]" value="{{ old('google_form_mapping.author_phone_column', $mapping['author_phone_column']) }}" required placeholder="Registered Author's Phone Number">
                        </div>
                        <div>
                            <label class="form-label text-xs font-bold">Manuscript Source Upload Column Header <span class="text-rose-500">*</span></label>
                            <input class="form-input text-xs" name="google_form_mapping[manuscript_file_column]" value="{{ old('google_form_mapping.manuscript_file_column', $mapping['manuscript_file_column']) }}" required placeholder="Upload the Manuscript Source">
                        </div>
                    </div>
                </div>

                <div class="border-t border-orange/15 pt-5">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <h4 class="font-extrabold text-navy text-sm flex items-center gap-2">
                                <span>✨</span> Custom / Optional Additional Fields Mapping
                            </h4>
                            <p class="text-xs text-muted mt-0.5">Add any additional Google Form questions or spreadsheet columns you want Paperflow to capture.</p>
                        </div>
                        <button type="button" @click="customFields.push({ label: '', column: '' })" class="btn btn-secondary text-xs py-1.5 px-3 font-bold inline-flex items-center gap-1 shadow-2xs hover:border-orange hover:text-orange">
                            <span>➕</span> Add Optional Field
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <template x-for="(field, index) in customFields" :key="index">
                            <div class="flex items-center gap-2.5 p-3 rounded-xl bg-white border border-navy/10 text-xs shadow-2xs">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1 min-w-0">
                                    <div>
                                        <label class="block text-[11px] font-bold text-navy/70 mb-1">Field Label in Paperflow</label>
                                        <input class="form-input text-xs py-1.5" :name="`google_form_mapping[custom_fields][${index}][label]`" x-model="field.label" placeholder="e.g. Presenter Name" required>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-navy/70 mb-1">Google Form / Spreadsheet Column Header</label>
                                        <input class="form-input text-xs py-1.5" :name="`google_form_mapping[custom_fields][${index}][column]`" x-model="field.column" placeholder="e.g. Name of Presenter" required>
                                    </div>
                                </div>
                                <button type="button" @click="customFields.splice(index, 1)" class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition shrink-0 self-end mb-0.5 font-bold" title="Remove optional field">
                                    🗑️ Remove
                                </button>
                            </div>
                        </template>
                        
                        <div x-show="customFields.length === 0" class="text-xs text-muted italic bg-white p-4 rounded-xl border border-dashed border-navy/15 text-center">
                            No optional custom fields added yet. Click "+ Add Optional Field" above to capture extra Google Form questions.
                        </div>
                    </div>
                </div>
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
