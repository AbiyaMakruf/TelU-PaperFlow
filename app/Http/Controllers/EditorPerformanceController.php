<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Models\Assignment;
use App\Models\Conference;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditorPerformanceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $ids = $request->user()->isSuperAdmin() ? Conference::pluck('id') : $request->user()->conferenceMemberships()->where('is_active', true)->pluck('conference_id');
        $rows = Assignment::with(['user', 'submission'])->where('role', ConferenceRole::Editorial)
            ->whereHas('submission', fn ($q) => $q->whereIn('conference_id', $ids))->get()->groupBy('user_id')
            ->map(function ($items) {
                $unique = $items->unique('submission_id');

                return (object) ['name' => $items->first()->user->name, 'paper_count' => $unique->count(),
                    'avg_days' => $unique->avg(fn ($a) => $a->assigned_at->diffInMinutes($a->submission->completed_at ?? now()) / 1440),
                    'overdue_count' => $unique->filter(fn ($a) => $a->submission->isOverdue())->count()];
            })->sortByDesc('paper_count')->values();

        return view('operations.editor-performance', compact('rows'));
    }
}
