<?php

namespace App\Http\Controllers;

use App\Models\Conference;
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
            return back()->with('status', 'Workspace diubah ke Semua Conference.');
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Validate that user has access to this conference
        if (! $user->isSuperAdmin()) {
            $hasAccess = $user->conferenceMemberships()
                ->where('conference_id', $conferenceId)
                ->exists();

            if (! $hasAccess) {
                return back()->withErrors(['conference_id' => 'Anda tidak memiliki akses ke conference tersebut.']);
            }
        }

        $conference = Conference::findOrFail($conferenceId);
        session(['active_conference_id' => $conference->id]);

        return back()->with('status', "Workspace aktif diubah ke {$conference->name}.");
    }
}
