<x-layouts.app title="Template email" :heading="$conference->name">
    <a class="back-link" href="{{ route('conferences.show', $conference) }}">&larr; Kembali ke conference</a>
    <div class="mt-5"><p class="eyebrow">Communication</p><h1 class="page-title">Template email</h1><p class="page-subtitle">Variabel: <code>@{{conference}}</code>, <code>@{{paper_code}}</code>, <code>@{{author_name}}</code>, <code>@{{portal_url}}</code>, <code>@{{feedback}}</code>, <code>@{{deadline}}</code>, <code>@{{editor_name}}</code>, <code>@{{editor_job_title}}</code>, <code>@{{editor_affiliation}}</code>, dan <code>@{{editor_whatsapp_url}}</code>.</p></div>
    <form method="POST" action="{{ route('conferences.email-templates.update', $conference) }}" class="mt-7 space-y-5">@csrf @method('PUT')
        <section class="card p-6">
            <h2 class="font-black text-navy">Identitas pengirim</h2>
            <p class="mt-1 text-sm text-muted">Nama ini tampil di inbox author. Alamat pengirim tetap {{ config('mail.from.address') }}.</p>
            <label class="mt-5 block"><span class="form-label">Nama pengirim</span><input class="form-input" name="email_sender_name" value="{{ old('email_sender_name', $conference->email_sender_name ?: $conference->name) }}" maxlength="255" placeholder="Contoh: {{ $conference->name }} Editorial Team"></label>
            <label class="mt-5 block"><span class="form-label">Default CC conference</span><input class="form-input" name="conference_default_cc" value="{{ old('conference_default_cc', implode(', ', $conference->defaultCc())) }}" placeholder="chair@example.com, publication@example.com"><span class="mt-1 block text-xs text-muted">Muncul otomatis pada form editorial dan tetap dapat dihapus sebelum email dikirim.</span></label>
        </section>
        @foreach($templates as $template)
            <section class="card p-6" x-data="{
                subject: @js(old('templates.'.$template->id.'.subject', $template->subject)),
                body: @js(old('templates.'.$template->id.'.body', $template->body)),
                showPreview: false,
                render(text) {
                    return text.replace(/\{\{conference\}\}/g, '{{ $conference->name }}')
                               .replace(/\{\{paper_code\}\}/g, 'CONF-88A123')
                               .replace(/\{\{author_name\}\}/g, 'Dr. Author')
                               .replace(/\{\{portal_url\}\}/g, 'https://paperflow.id/submission/access/demo-token')
                               .replace(/\{\{feedback\}\}/g, 'Mohon perbaiki format referensi sesuai IEEE.')
                               .replace(/\{\{deadline\}\}/g, 'April 21, 2026 at 23:59 GMT+7')
                               .replace(/\{\{editor_name\}\}/g, '{{ auth()->user()->name }}')
                               .replace(/\{\{editor_job_title\}\}/g, '{{ auth()->user()->job_title ?: 'Publication Committee' }}')
                               .replace(/\{\{editor_affiliation\}\}/g, '{{ auth()->user()->affiliation ?: $conference->name }}')
                               .replace(/\{\{editor_whatsapp_url\}\}/g, '{{ auth()->user()->whatsapp() ? 'https://wa.me/'.\App\Services\PhoneNumber::whatsappDigits(auth()->user()->whatsapp()) : '-' }}');
                }
            }">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-orange">{{ str_replace('_', ' ', $template->key) }}</p>
                        <h2 class="mt-1 font-black text-navy">{{ $template->subject }}</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="showPreview = !showPreview" class="btn btn-secondary text-xs py-1.5 px-3">
                            <span x-text="showPreview ? 'Sembunyikan Preview' : '👁️ Live Preview'"></span>
                        </button>
                        <label class="check-row">
                            <input type="checkbox" name="templates[{{ $template->id }}][is_enabled]" value="1" @checked($template->is_enabled)>
                            <span>Aktif</span>
                        </label>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-1">
                    <label>
                        <span class="form-label">Subject</span>
                        <input class="form-input" name="templates[{{ $template->id }}][subject]" x-model="subject" required>
                    </label>
                    <label>
                        <span class="form-label">Isi email</span>
                        <textarea class="form-input min-h-44 py-3 font-mono" name="templates[{{ $template->id }}][body]" x-model="body" required></textarea>
                    </label>
                    <label>
                        <span class="form-label">Default CC</span>
                        <input class="form-input" name="templates[{{ $template->id }}][default_cc]" value="{{ old('templates.'.$template->id.'.default_cc', implode(', ', $template->default_cc ?? [])) }}" placeholder="editorial@example.com, chair@example.com">
                    </label>
                </div>

                <!-- Live Preview Pane -->
                <div x-show="showPreview" x-collapse class="mt-4 p-4 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 space-y-3">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Live Email Preview</div>
                    <div class="p-3 bg-white rounded-lg border border-slate-200 shadow-sm">
                        <div class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2 mb-2">
                            Subject: <span x-text="render(subject)"></span>
                        </div>
                        <div class="text-sm whitespace-pre-wrap font-sans text-slate-700 leading-relaxed" x-text="render(body)"></div>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-3 rounded-xl border border-orange/20 bg-orange/5 p-4 sm:flex-row sm:items-end"><label class="flex-1"><span class="form-label">Kirim preview ke</span><input class="form-input" type="email" name="test_recipients[{{ $template->id }}]" value="{{ old('test_recipients.'.$template->id, auth()->user()->email) }}"></label><button class="btn btn-secondary" name="test_template_id" value="{{ $template->id }}" formaction="{{ route('conferences.email-templates.test', $conference) }}">Kirim test email</button></div>
            </section>
        @endforeach
        <div class="sticky bottom-4 flex justify-end"><button class="btn btn-primary shadow-xl">Simpan semua template</button></div>
    </form>
</x-layouts.app>
