@if (session('success'))
    <div class="mb-5 rounded-xl border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success">{{ session('success') }}</div>
@endif
@if (session('status'))
    <div class="mb-5 rounded-xl border border-info/20 bg-info/10 px-4 py-3 text-sm font-medium text-info">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-5 rounded-xl border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
        <p class="font-bold">Periksa kembali data berikut:</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
