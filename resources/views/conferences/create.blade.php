<x-layouts.app title="New Conference · Paperflow" heading="New Conference">
    <div class="max-w-4xl">
        <a href="{{ route('conferences.index') }}" class="back-link">&larr; Back</a>
        <h1 class="page-title mt-4">Create Conference</h1>
        <p class="page-subtitle">Initial submission form, review checklists, and email templates will be generated automatically.</p>
        <x-flash />
        <form method="POST" action="{{ route('conferences.store') }}" class="card mt-7 space-y-6 p-6">
            @csrf
            @include('conferences._form')
            <div class="flex justify-end gap-3">
                <a href="{{ route('conferences.index') }}" class="btn btn-ghost">Cancel</a>
                <button class="btn btn-primary">Create Conference</button>
            </div>
        </form>
    </div>
</x-layouts.app>
