<x-layouts.app :title="'Preview '.$file->original_name" heading="File Preview">
    <a class="back-link" href="{{ route('submissions.show',$submission) }}">&larr; Back to paper</a>
    <div class="card mt-5 p-7">
        <div class="flex justify-between gap-4">
            <div>
                <p class="eyebrow">DOCX Preview</p>
                <h1 class="page-title">{{ $file->original_name }}</h1>
            </div>
            <a class="btn btn-secondary" href="{{ route('submissions.files.download',[$submission,$file]) }}">Download</a>
        </div>
        <article class="mt-7 whitespace-pre-wrap border-t border-navy/10 pt-7 text-sm leading-7">{{ $text }}</article>
    </div>
</x-layouts.app>
