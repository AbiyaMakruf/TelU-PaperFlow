<x-layouts.public :title="$submission->paper_code">
    <div class="mx-auto max-w-5xl">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
            <div>
                <p class="eyebrow">Author portal &middot; {{ $submission->conference->name }}</p>
                <h1 class="page-title">{{ $submission->paper_code }}</h1>
                <p class="page-subtitle">{{ $submission->title }}</p>
            </div>
            <span class="badge badge-{{ $submission->status->color() }}">{{ $submission->status->label() }}</span>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1.4fr_.6fr]">
            <section class="space-y-6">
                @if ($submission->feedback->isNotEmpty())
                    <div class="card p-6">
                        <h2 class="text-lg font-black text-navy">Catatan dari tim</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($submission->feedback as $item)
                                <div class="rounded-xl bg-warm p-4 text-sm leading-6">{!! nl2br(e($item->body)) !!}<p class="mt-2 text-xs text-muted">{{ $item->created_at->timezone($submission->conference->timezone)->format('d M Y H:i') }}</p></div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (in_array($submission->status, [\App\Enums\SubmissionStatus::NeedsAuthorCorrection, \App\Enums\SubmissionStatus::WaitingAuthorRevision], true))
                    <form method="POST" action="{{ route('author.revision', $token) }}" enctype="multipart/form-data" class="card p-6">
                        @csrf
                        <h2 class="text-lg font-black text-navy">Unggah revisi</h2>
                        <label class="mt-5 block"><span class="form-label">File editable baru *</span><input class="form-input py-3" type="file" name="paper_file" accept=".doc,.docx,.tex,.zip" required></label>
                        <label class="mt-5 block"><span class="form-label">Catatan perubahan</span><textarea class="form-input min-h-24 py-3" name="notes"></textarea></label>
                        <button class="btn btn-primary mt-5">Kirim revisi</button>
                    </form>
                @endif

                <div class="card overflow-hidden">
                    <div class="p-6"><h2 class="text-lg font-black text-navy">Riwayat file</h2></div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Versi</th><th>File</th><th>Sumber</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($submission->files as $file)
                                    <tr><td>v{{ $file->version_number }}</td><td><p class="font-bold text-navy">{{ $file->label }}</p><p class="text-xs text-muted">{{ $file->original_name }}</p></td><td>{{ ucfirst($file->source) }}</td><td><a class="font-bold text-orange" href="{{ route('author.files.download', [$token, $file]) }}">Download</a></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <aside class="card h-fit p-6">
                <h2 class="text-lg font-black text-navy">Timeline</h2>
                <ol class="mt-5 space-y-5 border-l-2 border-navy/10 pl-5">
                    @foreach ($submission->statusHistory as $history)
                        <li><span class="-ml-[27px] mr-3 inline-block size-3 rounded-full bg-orange ring-4 ring-warm"></span><span class="text-sm font-bold text-navy">{{ $history->to_status->label() }}</span><p class="mt-1 text-xs text-muted">{{ $history->created_at->timezone($submission->conference->timezone)->format('d M Y H:i') }}</p></li>
                    @endforeach
                </ol>
            </aside>
        </div>
    </div>
</x-layouts.public>
