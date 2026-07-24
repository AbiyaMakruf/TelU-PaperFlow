<x-layouts.app :title="'Form Builder · '.$conference->name" heading="Form Builder">
    @php($fields = old('fields', $form->schema ?? []))
    <div class="max-w-5xl space-y-6">
        <x-conference-header :conference="$conference" active="form" />
        <div class="flex items-center justify-between gap-4 bg-white p-4 rounded-xl border border-navy/10 shadow-sm">
            <div>
                <h2 class="font-black text-navy text-lg">Custom Form Builder Fields</h2>
                <p class="text-xs text-muted">Draft version {{ $form->version }}. Core paper fields and editable manuscript uploads are included automatically.</p>
            </div>
            <span class="badge badge-warning text-xs font-black">{{ ucfirst($form->status) }}</span>
        </div>
        <div class="card mt-7 border-orange/20 bg-orange/5 p-5 text-sm leading-6">
            <strong class="text-navy">Core fields:</strong> Paper ID, title, corresponding author name/email/phone, author list, and editable manuscript file. Add custom conference fields below.
        </div>
        <form method="POST" action="{{ route('conferences.form.update', [$conference, $form]) }}" class="mt-5" data-builder="fields">
            @csrf
            @method('PUT')
            <div class="space-y-4" data-builder-list>
                @foreach($fields as $index => $field)
                    <div class="card grid gap-4 p-5 md:grid-cols-2" data-builder-item>
                        <div><label class="form-label">Label</label><input class="form-input" name="fields[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" required></div>
                        <div><label class="form-label">Key</label><input class="form-input" name="fields[{{ $index }}][key]" value="{{ $field['key'] ?? '' }}" pattern="[a-z][a-z0-9_]*" required></div>
                        <div>
                            <label class="form-label">Type</label>
                            <select class="form-input" name="fields[{{ $index }}][type]">
                                @foreach(['text'=>'Text','email'=>'Email','tel'=>'Phone','number'=>'Number','url'=>'URL','date'=>'Date','textarea'=>'Textarea','select'=>'Select (Dropdown)','radio'=>'Single Choice (Radio)','checkbox'=>'Checkbox'] as $value=>$label)
                                    <option value="{{ $value }}" @selected(($field['type'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="form-label">Help Text</label><input class="form-input" name="fields[{{ $index }}][help]" value="{{ $field['help'] ?? '' }}"></div>
                        <div class="md:col-span-2">
                            <label class="form-label">Options <span class="font-normal text-muted">(one per line; for select/radio)</span></label>
                            <textarea class="form-input min-h-20 py-3" name="fields[{{ $index }}][options]">{{ is_array($field['options'] ?? null) ? implode("\n", $field['options']) : ($field['options'] ?? '') }}</textarea>
                        </div>
                        <div class="md:col-span-2 flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="fields[{{ $index }}][required]" value="1" @checked($field['required'] ?? false)> Required field</label>
                            <button type="button" class="text-sm font-bold text-danger" data-builder-remove>Delete field</button>
                        </div>
                    </div>
                @endforeach
            </div>
            <template data-builder-template>
                <div class="card grid gap-4 p-5 md:grid-cols-2" data-builder-item>
                    <div><label class="form-label">Label</label><input class="form-input" name="fields[__INDEX__][label]" required></div>
                    <div><label class="form-label">Key</label><input class="form-input" name="fields[__INDEX__][key]" pattern="[a-z][a-z0-9_]*" placeholder="e.g. track_name" required></div>
                    <div>
                        <label class="form-label">Type</label>
                        <select class="form-input" name="fields[__INDEX__][type]">
                            <option value="text">Text</option><option value="email">Email</option><option value="tel">Phone</option><option value="number">Number</option><option value="url">URL</option><option value="date">Date</option><option value="textarea">Textarea</option><option value="select">Select (Dropdown)</option><option value="radio">Single Choice (Radio)</option><option value="checkbox">Checkbox</option>
                        </select>
                    </div>
                    <div><label class="form-label">Help Text</label><input class="form-input" name="fields[__INDEX__][help]"></div>
                    <div class="md:col-span-2"><label class="form-label">Options</label><textarea class="form-input min-h-20 py-3" name="fields[__INDEX__][options]"></textarea></div>
                    <div class="md:col-span-2 flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="fields[__INDEX__][required]" value="1"> Required field</label>
                        <button type="button" class="text-sm font-bold text-danger" data-builder-remove>Delete field</button>
                    </div>
                </div>
            </template>
            <div class="mt-5 flex flex-wrap justify-between gap-3">
                <button type="button" class="btn btn-secondary" data-builder-add>+ Add Field</button>
                <div class="flex gap-3"><button class="btn btn-primary">Save Draft</button></div>
            </div>
        </form>
        <form method="POST" action="{{ route('conferences.form.publish', [$conference, $form]) }}" class="card mt-6 flex flex-wrap items-center justify-between gap-4 p-5">
            @csrf
            <div>
                <p class="font-bold text-navy">Publish Version {{ $form->version }}</p>
                <p class="mt-1 text-sm text-muted">The currently active form will be archived. Historical submissions remain unaffected.</p>
            </div>
            <button class="btn btn-primary">Publish Form</button>
        </form>
    </div>
</x-layouts.app>
