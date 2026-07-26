<div x-data="{
        show: false,
        message: '',
        type: 'success',
        init() {
            @if(session('success'))
                this.trigger(@js(session('success')), 'success');
            @elseif(session('error'))
                this.trigger(@js(session('error')), 'error');
            @elseif(session('status'))
                this.trigger(@js(session('status')), 'success');
            @endif

            window.addEventListener('paperflow-toast', (e) => {
                if (e.detail) {
                    this.trigger(e.detail.message, e.detail.type || 'success');
                }
            });
        },
        trigger(msg, type = 'success') {
            this.message = msg;
            this.type = type;
            this.show = true;
            setTimeout(() => { this.show = false; }, 4500);
        }
     }"
     x-init="init()"
     class="relative">
    <template x-teleport="body">
        <div x-show="show" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-[-16px] scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-[-16px] scale-95"
             class="fixed top-6 right-6 z-[99999] flex items-start gap-3 rounded-2xl p-4 shadow-2xl border min-w-[320px] max-w-md bg-slate-900 text-white"
             :class="type === 'success' ? 'border-emerald-500/50 ring-2 ring-emerald-500/20' : 'border-rose-500/50 ring-2 ring-rose-500/20'"
             style="box-shadow: 0 25px 40px -10px rgba(15, 23, 42, 0.65);">
            <div class="rounded-xl p-2 shrink-0 font-black text-sm"
                 :class="type === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'">
                <span x-text="type === 'success' ? '✓' : '✕'"></span>
            </div>
            <div class="min-w-0 flex-1 py-0.5">
                <p class="font-extrabold text-[11px] tracking-wider uppercase"
                   :class="type === 'success' ? 'text-emerald-400' : 'text-rose-400'"
                   x-text="type === 'success' ? 'Success Notification' : 'Attention Required'"></p>
                <p x-text="message" class="text-xs font-semibold text-slate-100 mt-0.5 leading-relaxed break-words"></p>
            </div>
            <button type="button" @click="show = false" class="text-slate-400 hover:text-white font-bold p-1 text-base leading-none rounded-lg hover:bg-white/10 transition">&times;</button>
        </div>
    </template>
</div>
