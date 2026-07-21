<x-layouts.app title="Conference baru · Paperflow" heading="Conference baru">
    <div class="max-w-4xl"><a href="{{ route('conferences.index') }}" class="back-link">← Kembali</a><h1 class="page-title mt-4">Buat conference</h1><p class="page-subtitle">Form, checklist, dan email template awal dibuat otomatis.</p><x-flash />
        <form method="POST" action="{{ route('conferences.store') }}" class="card mt-7 space-y-6 p-6">@csrf @include('conferences._form')<div class="flex justify-end gap-3"><a href="{{ route('conferences.index') }}" class="btn btn-ghost">Batal</a><button class="btn btn-primary">Buat conference</button></div></form>
    </div>
</x-layouts.app>
