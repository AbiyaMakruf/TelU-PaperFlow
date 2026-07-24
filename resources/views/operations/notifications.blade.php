<x-layouts.app title="Notifications · Paperflow" heading="Notifications">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <p class="eyebrow">Inbox</p>
            <h1 class="page-title">Notifications</h1>
        </div>
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-secondary w-full sm:w-auto">Mark all as read</button>
        </form>
    </div>
    <div class="mt-6 space-y-3">
        @forelse($notifications as $item)
            <a href="{{ route('notifications.read',$item->id) }}" class="card block p-5 {{ $item->read_at ? 'opacity-70' : 'border-orange/30' }}">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                    <strong class="text-navy text-sm">{{ $item->data['title'] ?? 'Notification' }}</strong>
                    <span class="text-xs text-muted">{{ $item->created_at->diffForHumans() }}</span>
                </div>
                <p class="mt-2 text-xs sm:text-sm text-muted">{{ $item->data['message'] ?? '' }}</p>
            </a>
        @empty
            <div class="card p-10 text-center text-muted">No notifications found.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $notifications->links() }}</div>
</x-layouts.app>
