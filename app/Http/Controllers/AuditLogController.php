<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Models\AuditLog;
use App\Models\Conference;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $ids = $request->user()->isSuperAdmin()
            ? Conference::pluck('id')
            : $request->user()->conferenceMemberships()->where('is_active', true)->where('role', ConferenceRole::Admin)->pluck('conference_id');
        abort_if(! $request->user()->isSuperAdmin() && $ids->isEmpty(), 403);
        $logs = AuditLog::with(['user', 'conference'])->whereIn('conference_id', $ids)
            ->when($request->filled('conference'), fn ($q) => $q->where('conference_id', $request->string('conference')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', 'like', '%'.$request->string('event').'%'))
            ->latest('created_at')->paginate(30)->withQueryString();
        $conferences = Conference::whereIn('id', $ids)->orderBy('name')->get();

        return view('operations.audit', compact('logs', 'conferences'));
    }
}
