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

        @can('delete', $conference)
            <div class="card p-6 border border-rose-200 bg-rose-50/50 space-y-4">
                <div>
                    <h3 class="font-extrabold text-rose-900 text-sm flex items-center gap-2">
                        <span>⚠️</span> Danger Zone — Delete Conference
                    </h3>
                    <p class="text-xs text-rose-700 mt-1 leading-relaxed">
                        Deleting this conference will permanently remove all configuration, assigned staff memberships, and all associated paper submissions. This action is restricted to Superadmin and cannot be undone.
                    </p>
                </div>
                <form method="POST" action="{{ route('conferences.destroy', $conference) }}" onsubmit="return confirm('Are you sure you want to PERMANENTLY DELETE conference &quot;{{ $conference->name }}&quot;? All associated papers and settings will be deleted.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold py-2 px-4 shadow-2xs">
                        🗑️ Delete Conference Permanently
                    </button>
                </form>
            </div>
        @endcan
    </div>
</x-layouts.app>
