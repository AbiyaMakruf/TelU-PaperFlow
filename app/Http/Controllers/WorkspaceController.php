<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'conference_id' => ['nullable', 'string'],
        ]);

        $conferenceId = $validated['conference_id'] ?? null;

        if ($conferenceId === 'all' || empty($conferenceId)) {
            session()->forget('active_conference_id');

            return back()->with('status', 'Active workspace switched to All Conferences.');
        }

        /** @var User $user */
        $user = auth()->user();

        // Validate that user has access to this conference
        if (! $user->isSuperAdmin()) {
            $hasAccess = $user->conferenceMemberships()
                ->where('conference_id', $conferenceId)
                ->exists();

            if (! $hasAccess) {
                return back()->withErrors(['conference_id' => 'You do not have access to the requested conference workspace.']);
            }
        }

        $conference = Conference::findOrFail($conferenceId);
        session(['active_conference_id' => $conference->id]);

        return back()->with('status', "Active workspace switched to {$conference->name}.");
    }
}
