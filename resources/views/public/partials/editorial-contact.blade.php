<div class="card p-4 sm:p-6 min-w-0">
    <h2 class="text-base sm:text-lg font-black text-navy pb-3 border-b border-navy/10">Editorial Contact</h2>

    @if($submission->editor)
        @php
            $editorWa = $submission->editor->whatsapp();
            $editorWaDigits = \App\Services\PhoneNumber::whatsappDigits($submission->editor->whatsapp());
        @endphp
        <div class="mt-4 space-y-3.5 text-xs">
            <div class="space-y-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-muted block">PIC Editor Name</span>
                <p class="font-extrabold text-navy text-sm leading-snug break-words">{{ $submission->editor->name }}</p>
            </div>

            <div class="pt-3 border-t border-slate-100 space-y-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-muted block">Email Address</span>
                <a href="mailto:{{ $submission->editor->email }}" class="font-bold text-orange hover:underline break-all block text-xs leading-snug">
                    {{ $submission->editor->email ?: '-' }}
                </a>
            </div>

            <div class="pt-3 border-t border-slate-100 space-y-1.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-muted block">Mobile / WhatsApp</span>
                @if($editorWaDigits)
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="font-bold text-navy text-xs">{{ $editorWa }}</span>
                        <a href="https://wa.me/{{ $editorWaDigits }}?text={{ rawurlencode('Hello ' . $submission->editor->name . ', I am the corresponding author of Paper ID ' . $submission->paper_id . ' (' . $submission->title . ').') }}" 
                           target="_blank" rel="noopener" 
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-extrabold bg-[#25D366] text-white hover:bg-[#1faa52] rounded-lg shadow-2xs transition">
                            <svg class="size-3.5 fill-current shrink-0" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.447-.52.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.572-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414zM12.012 2.004c-5.508 0-9.982 4.474-9.982 9.982 0 1.764.459 3.483 1.332 4.995L2 22l5.147-1.349a9.96 9.96 0 004.865 1.335h.004c5.507 0 9.982-4.474 9.982-9.982 0-2.668-1.039-5.176-2.927-7.062a9.918 9.918 0 00-7.059-2.938z"/>
                            </svg>
                            <span>WhatsApp</span>
                        </a>
                    </div>
                @else
                    <span class="font-bold text-navy block leading-snug">-</span>
                @endif
            </div>
        </div>
    @else
        <div class="mt-4 p-3.5 bg-slate-50/80 rounded-xl border border-slate-200/80 text-xs text-muted leading-relaxed">
            <span>An Editorial PIC has not been assigned to this paper yet.</span>
        </div>
    @endif
</div>
