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
            <h3 class="font-black text-navy">Public Branding</h3>
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
        </div>
    @endif
</div>
