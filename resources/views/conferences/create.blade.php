<x-layouts.app title="New Conference · Paperflow" heading="New Conference">
    <div class="max-w-4xl">
        <a href="{{ route('conferences.index') }}" class="back-link">&larr; Back</a>
        <h1 class="page-title mt-4">Create Conference</h1>
        <p class="page-subtitle">Initial submission form, review checklists, and email templates will be generated automatically.</p>
        <x-flash />
        <form method="POST" action="{{ route('conferences.store') }}" enctype="multipart/form-data" class="card mt-7 space-y-6 p-6 sm:p-8">
            @csrf
            @include('conferences._form')
            <div class="flex items-center justify-end gap-3 pt-5 border-t border-navy/10">
                <a href="{{ route('conferences.index') }}" class="btn btn-secondary text-xs font-bold">Cancel</a>
                <button type="submit" class="btn btn-primary text-xs font-extrabold shadow-sm">Create Conference</button>
            </div>
        </form>
    </div>
</x-layouts.app>
