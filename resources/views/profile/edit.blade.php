<x-layouts.app title="Profil" heading="Profil saya">
    <div class="mx-auto max-w-3xl">
        <p class="eyebrow">Personal identity</p><h1 class="page-title">Profil saya</h1>
        <p class="page-subtitle">Identitas ini digunakan pada signature email dan tautan WhatsApp editorial.</p>
        <form method="POST" action="{{ route('profile.update') }}" class="card mt-7 grid gap-5 p-5 sm:grid-cols-2 sm:p-7">@csrf @method('PUT')
            <label><span class="form-label">Nama lengkap *</span><input class="form-input" name="name" value="{{ old('name',$user->name) }}" required></label>
            <label><span class="form-label">Username</span><input class="form-input bg-slate-100" value="{{ $user->username }}" disabled></label>
            <label><span class="form-label">Email *</span><input class="form-input" type="email" name="email" value="{{ old('email',$user->email) }}" required></label>
            <label><span class="form-label">Jabatan / peran publikasi</span><input class="form-input" name="job_title" value="{{ old('job_title',$user->job_title) }}" placeholder="Publication Committee"></label>
            <label class="sm:col-span-2"><span class="form-label">Institusi / afiliasi</span><input class="form-input" name="affiliation" value="{{ old('affiliation',$user->affiliation) }}"></label>
            <label><span class="form-label">Kode negara WhatsApp</span><select class="form-input" name="whatsapp_country_code"><option value="">Pilih...</option>@foreach($countryCodes as $code=>$label)<option value="{{ $code }}" @selected(old('whatsapp_country_code',$user->whatsapp_country_code)===$code)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="form-label">Nomor WhatsApp</span><input class="form-input" name="whatsapp_number" value="{{ old('whatsapp_number',$user->whatsapp_number) }}" placeholder="81234567890"></label>
            <div class="sm:col-span-2"><button class="btn btn-primary w-full sm:w-auto">Simpan profil</button></div>
        </form>
    </div>
</x-layouts.app>
