<x-layouts.app :title="'Checklist '.$conference->name" heading="Checklist">
    <div class="max-w-5xl space-y-6">
        <x-conference-header :conference="$conference" active="checklists" />
        <x-flash />
        <form method="POST" action="{{ route('conferences.checklists.update', $conference) }}" class="mt-7 space-y-6">@csrf @method('PUT')
            @foreach(\App\Enums\ReviewStage::cases() as $stage)
                @php($template = $templates->get($stage->value))
                <section class="card p-6" data-builder="{{ $stage->value }}"><div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">{{ $stage->label() }}</p><h2 class="mt-1 text-xl font-black text-navy">{{ $stage === \App\Enums\ReviewStage::Editorial ? 'Pemeriksaan editorial' : 'Final review' }}</h2></div><div class="w-full sm:w-72"><label class="form-label">Nama template</label><input class="form-input" name="templates[{{ $stage->value }}][name]" value="{{ old("templates.{$stage->value}.name", $template?->name) }}" required></div></div>
                    <div class="mt-6 space-y-3" data-builder-list>
                        @foreach(old("templates.{$stage->value}.items", $template?->items?->toArray() ?? []) as $index => $item)
                            <div class="rounded-xl border border-navy/10 bg-warm/40 p-4" data-builder-item><div class="grid gap-3 md:grid-cols-[1fr_1.4fr]"><input class="form-input" name="templates[{{ $stage->value }}][items][{{ $index }}][title]" value="{{ $item['title'] ?? '' }}" placeholder="Judul pemeriksaan" required><input class="form-input" name="templates[{{ $stage->value }}][items][{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" placeholder="Deskripsi/instruksi"></div><div class="mt-3 flex justify-between"><label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="templates[{{ $stage->value }}][items][{{ $index }}][is_required]" value="1" @checked($item['is_required'] ?? false)> Wajib</label><button type="button" class="text-sm font-bold text-danger" data-builder-remove>Hapus</button></div></div>
                        @endforeach
                    </div>
                    <template data-builder-template><div class="rounded-xl border border-navy/10 bg-warm/40 p-4" data-builder-item><div class="grid gap-3 md:grid-cols-[1fr_1.4fr]"><input class="form-input" name="templates[{{ $stage->value }}][items][__INDEX__][title]" placeholder="Judul pemeriksaan" required><input class="form-input" name="templates[{{ $stage->value }}][items][__INDEX__][description]" placeholder="Deskripsi/instruksi"></div><div class="mt-3 flex justify-between"><label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="templates[{{ $stage->value }}][items][__INDEX__][is_required]" value="1" checked> Wajib</label><button type="button" class="text-sm font-bold text-danger" data-builder-remove>Hapus</button></div></div></template>
                    <button type="button" class="btn btn-secondary mt-4" data-builder-add>+ Tambah pemeriksaan</button>
                </section>
            @endforeach
            <div class="flex justify-end"><button class="btn btn-primary">Simpan checklist</button></div>
        </form>
    </div>
</x-layouts.app>
