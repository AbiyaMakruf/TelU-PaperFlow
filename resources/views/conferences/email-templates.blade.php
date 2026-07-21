<x-layouts.app title="Template email" :heading="$conference->name">
    <a class="back-link" href="{{ route('conferences.show', $conference) }}">&larr; Kembali ke conference</a>
    <div class="mt-5"><p class="eyebrow">Communication</p><h1 class="page-title">Template email</h1><p class="page-subtitle">Atur pesan otomatis dan CC internal. Variabel yang tersedia: <code>@{{conference}}</code>, <code>@{{paper_code}}</code>, <code>@{{author_name}}</code>, <code>@{{portal_url}}</code>, dan <code>@{{feedback}}</code>.</p></div>
    <form method="POST" action="{{ route('conferences.email-templates.update', $conference) }}" class="mt-7 space-y-5">@csrf @method('PUT')
        <section class="card p-6">
            <h2 class="font-black text-navy">Identitas pengirim</h2>
            <p class="mt-1 text-sm text-muted">Nama ini tampil di inbox author. Alamat pengirim tetap {{ config('mail.from.address') }}.</p>
            <label class="mt-5 block"><span class="form-label">Nama pengirim</span><input class="form-input" name="email_sender_name" value="{{ old('email_sender_name', $conference->email_sender_name ?: $conference->name) }}" maxlength="255" placeholder="Contoh: {{ $conference->name }} Editorial Team"></label>
        </section>
        @foreach($templates as $template)
            <section class="card p-6">
                <div class="flex items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wider text-orange">{{ str_replace('_', ' ', $template->key) }}</p><h2 class="mt-1 font-black text-navy">{{ $template->subject }}</h2></div><label class="check-row"><input type="checkbox" name="templates[{{ $template->id }}][is_enabled]" value="1" @checked($template->is_enabled)><span>Aktif</span></label></div>
                <div class="mt-5 grid gap-4">
                    <label><span class="form-label">Subject</span><input class="form-input" name="templates[{{ $template->id }}][subject]" value="{{ old('templates.'.$template->id.'.subject', $template->subject) }}" required></label>
                    <label><span class="form-label">Isi email</span><textarea class="form-input min-h-44 py-3 font-mono" name="templates[{{ $template->id }}][body]" required>{{ old('templates.'.$template->id.'.body', $template->body) }}</textarea></label>
                    <label><span class="form-label">Default CC</span><input class="form-input" name="templates[{{ $template->id }}][default_cc]" value="{{ old('templates.'.$template->id.'.default_cc', implode(', ', $template->default_cc ?? [])) }}" placeholder="editorial@example.com, chair@example.com"></label>
                </div>
            </section>
        @endforeach
        <div class="sticky bottom-4 flex justify-end"><button class="btn btn-primary shadow-xl">Simpan semua template</button></div>
    </form>
</x-layouts.app>
