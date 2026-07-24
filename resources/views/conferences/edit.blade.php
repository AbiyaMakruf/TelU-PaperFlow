<x-layouts.app :title="'Edit Conference · '.$conference->name" heading="Conference Settings">
    <div class="max-w-4xl space-y-6">
        <x-conference-header :conference="$conference" active="settings" />
        <x-flash />
        <form method="POST" action="{{ route('conferences.update', $conference) }}" enctype="multipart/form-data" class="card mt-7 space-y-6 p-6">
            @csrf
            @method('PUT')
            @include('conferences._form', ['conference' => $conference])
            <div class="flex justify-end gap-3">
                <a href="{{ route('conferences.show', $conference) }}" class="btn btn-ghost">Cancel</a>
                <button class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</x-layouts.app>
