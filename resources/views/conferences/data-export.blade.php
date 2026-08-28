<x-layouts.app :title="'Data Export · '.$conference->name">
    <div class="w-full space-y-6" x-data="conferenceDataExport({
        defaultFields: @js($presets['operations']['fields']),
        allFields: @js(collect($fieldGroups)->pluck('fields')->collapse()->keys()->values()),
        presets: @js($presets),
    })">
        <x-conference-header :conference="$conference" active="data-export" />

        <section class="card overflow-hidden border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-navy/10 bg-slate-50/70 px-5 py-5 sm:px-7">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <span class="badge bg-navy/10 text-navy text-[10px] font-black uppercase tracking-wider">Conference reporting</span>
                        <h1 class="mt-2 text-xl font-black tracking-tight text-navy sm:text-2xl">Data Export</h1>
                        <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">
                            Create a customizable report for <strong>{{ $conference->name }}</strong>. All active conference staff can export the complete conference dataset.
                        </p>
                    </div>
                    <div class="shrink-0 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] font-bold text-emerald-900">
                        <span x-text="selectedFields.length"></span> fields selected
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('conferences.data-export.download', $conference) }}" class="space-y-6 p-5 sm:p-7" :target="format === 'pdf' ? '_blank' : '_self'">
                <section class="space-y-3">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-sm font-black text-navy">Start with a preset</h2>
                            <p class="mt-0.5 text-xs text-muted">You can still customize the selected fields below.</p>
                        </div>
                        <button type="button" @click="clearSelection()" class="text-xs font-bold text-slate-500 hover:text-navy">Clear selection</button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                        @foreach($presets as $key => $preset)
                            <button type="button" @click="applyPreset('{{ $key }}')" :class="activePreset === '{{ $key }}' ? 'border-navy bg-navy text-white shadow-sm' : 'border-slate-200 bg-white text-navy hover:border-navy/40 hover:bg-slate-50'" class="rounded-xl border px-3 py-3 text-left text-xs font-extrabold transition">
                                {{ $preset['label'] }}
                            </button>
                        @endforeach
                    </div>
                </section>

                <details class="rounded-2xl border border-slate-200 bg-slate-50/50" open>
                    <summary class="cursor-pointer list-none px-4 py-4 text-sm font-black text-navy marker:content-none sm:px-5">
                        <span class="flex items-center justify-between gap-3">
                            <span>Filter papers included in this export</span>
                            <span class="text-[11px] font-bold text-muted">Optional</span>
                        </span>
                    </summary>
                    <div class="grid gap-4 border-t border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-5 sm:p-5">
                        <label class="block text-xs font-bold text-navy">Paper status
                            <select name="status" class="form-select mt-1.5 w-full text-xs">
                                <option value="">All statuses</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-bold text-navy">Assigned editor
                            <select name="editor_id" class="form-select mt-1.5 w-full text-xs">
                                <option value="">All editors</option>
                                @foreach($editors as $editor)
                                    <option value="{{ $editor->id }}">{{ $editor->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-bold text-navy">Assigned reviewer
                            <select name="reviewer_id" class="form-select mt-1.5 w-full text-xs">
                                <option value="">All reviewers</option>
                                @foreach($reviewers as $reviewer)
                                    <option value="{{ $reviewer->id }}">{{ $reviewer->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-bold text-navy">Submitted from
                            <input name="date_from" type="date" class="form-input mt-1.5 w-full text-xs">
                        </label>
                        <label class="block text-xs font-bold text-navy">Submitted until
                            <input name="date_to" type="date" class="form-input mt-1.5 w-full text-xs">
                        </label>
                    </div>
                </details>

                <section class="space-y-3">
                    <div>
                        <h2 class="text-sm font-black text-navy">Choose the data to include</h2>
                        <p class="mt-0.5 text-xs text-muted">Paper ID is always included so every row remains identifiable.</p>
                    </div>
                    <div class="grid gap-3 lg:grid-cols-2">
                        @foreach($fieldGroups as $groupKey => $group)
                            <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white" open>
                                <summary class="cursor-pointer list-none border-b border-slate-100 bg-slate-50/70 px-4 py-3.5 text-sm font-black text-navy marker:content-none">
                                    <span class="flex items-center justify-between gap-3">
                                        <span>{{ $group['label'] }}</span>
                                        <button type="button" @click.stop="toggleGroup(@js(array_keys($group['fields'])))" class="text-[11px] font-bold text-orange hover:underline">Toggle group</button>
                                    </span>
                                </summary>
                                <div class="grid gap-x-4 gap-y-3 p-4 sm:grid-cols-2">
                                    @foreach($group['fields'] as $field => $label)
                                        <label class="flex cursor-pointer items-start gap-2.5 text-xs font-semibold text-slate-700">
                                            <input type="checkbox" name="fields[]" value="{{ $field }}" x-model="selectedFields" @if($field === 'paper_id') checked disabled @endif class="mt-0.5 size-4 rounded border-slate-300 text-navy focus:ring-navy">
                                            <span>{{ $label }}@if($field === 'paper_id') <span class="text-[10px] text-muted">(required)</span>@endif</span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>

                <section class="sticky bottom-3 z-20 rounded-2xl border border-navy/15 bg-white/95 p-4 shadow-xl backdrop-blur sm:flex sm:items-center sm:justify-between sm:gap-5">
                    <div>
                        <p class="text-xs font-black text-navy">Export format</p>
                        <p class="mt-0.5 text-[11px] text-muted">CSV and XLSX download directly. PDF opens as a print-ready report.</p>
                    </div>
                    <div class="mt-3 flex flex-col gap-2 sm:mt-0 sm:flex-row sm:items-center">
                        <select name="format" x-model="format" class="form-select text-xs font-bold">
                            <option value="csv">CSV</option>
                            <option value="xlsx">XLSX</option>
                            <option value="pdf">PDF / Print</option>
                        </select>
                        <button type="submit" :disabled="selectedFields.length === 0" class="btn btn-primary whitespace-nowrap px-5 py-2.5 text-xs font-extrabold disabled:cursor-not-allowed disabled:opacity-50">
                            Download Export
                        </button>
                    </div>
                </section>
            </form>
        </section>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('conferenceDataExport', ({ defaultFields, allFields, presets }) => ({
                selectedFields: [...defaultFields],
                allFields,
                presets,
                activePreset: 'operations',
                format: 'csv',
                applyPreset(key) {
                    this.activePreset = key;
                    this.selectedFields = key === 'full' ? [...this.allFields] : [...this.presets[key].fields];
                    if (!this.selectedFields.includes('paper_id')) this.selectedFields.unshift('paper_id');
                },
                clearSelection() {
                    this.activePreset = null;
                    this.selectedFields = ['paper_id'];
                },
                toggleGroup(fields) {
                    const allSelected = fields.every(field => this.selectedFields.includes(field));
                    this.activePreset = null;
                    if (allSelected) {
                        this.selectedFields = this.selectedFields.filter(field => field === 'paper_id' || !fields.includes(field));
                    } else {
                        this.selectedFields = [...new Set([...this.selectedFields, ...fields])];
                    }
                },
            }));
        });
    </script>
</x-layouts.app>
