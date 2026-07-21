<x-layouts.app :title="'Google Drive - '.$conference->name" :heading="$conference->name">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('conferences.show', $conference) }}" class="back-link">&larr; Kembali ke conference</a>
        <div class="mt-4"><p class="eyebrow">Integrasi penyimpanan</p><h1 class="page-title">Google Drive</h1><p class="page-subtitle">Hubungkan Drive untuk menerima salinan file editable dari form publik.</p></div>

        @if($errors->any())<div class="mt-6 rounded-xl border border-danger/20 bg-danger/8 p-4 text-sm text-danger">{{ $errors->first() }}</div>@endif
        @if(session('success'))<div class="mt-6 rounded-xl border border-success/20 bg-success/8 p-4 text-sm text-success">{{ session('success') }}</div>@endif

        <section class="card mt-6 p-6">
            <dl class="grid gap-5 text-sm sm:grid-cols-2">
                <div><dt class="text-muted">Konfigurasi OAuth</dt><dd class="mt-1 font-black {{ $drive->configured() ? 'text-success' : 'text-danger' }}">{{ $drive->configured() ? 'Lengkap' : 'Belum lengkap' }}</dd></div>
                <div><dt class="text-muted">Status koneksi</dt><dd class="mt-1 font-black {{ $drive->connected($conference) ? 'text-success' : 'text-muted' }}">{{ $drive->connected($conference) ? 'Terhubung' : 'Belum terhubung' }}</dd></div>
                <div><dt class="text-muted">Nama folder yang dicari</dt><dd class="mt-1 font-bold">{{ $drive->folderName($conference) }}</dd></div>
                <div><dt class="text-muted">Redirect URI</dt><dd class="mt-1 break-all font-mono text-xs">{{ config('services.google_drive.redirect_uri') ?: 'Belum diatur' }}</dd></div>
                @if($conference->google_drive_connected_at)<div><dt class="text-muted">Terhubung sejak</dt><dd class="mt-1 font-bold">{{ $conference->google_drive_connected_at->format('d M Y H:i') }}</dd></div>@endif
            </dl>

            <div class="mt-7 flex flex-wrap gap-3 border-t border-navy/10 pt-6">
                @if($drive->connected($conference))
                    <form method="POST" action="{{ route('conferences.drive.disconnect', $conference) }}">@csrf @method('DELETE')<button class="btn btn-secondary" type="submit">Putuskan koneksi</button></form>
                @else
                    <form method="POST" action="{{ route('conferences.drive.connect', $conference) }}">@csrf <button class="btn btn-primary" type="submit" @disabled(!$drive->configured())>Hubungkan Google Drive</button></form>
                @endif
            </div>
        </section>

        <div class="mt-5 rounded-xl bg-navy/5 p-5 text-sm leading-6 text-muted">
            Buat tepat satu folder di Google Drive dengan nama <strong class="text-navy">{{ $drive->folderName($conference) }}</strong>. Paperflow akan mencari folder tersebut saat otorisasi dan menggunakan nama paper code untuk memperbarui file bila nama yang sama sudah ada.
        </div>
    </div>
</x-layouts.app>
