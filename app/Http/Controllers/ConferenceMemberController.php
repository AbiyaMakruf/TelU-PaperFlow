<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Models\Conference;
use App\Models\ConferenceMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConferenceMemberController extends Controller
{
    public function index(Conference $conference): View
    {
        $this->authorize('manageMembers', $conference);
        $conference->load(['memberships.user']);
        $availableUsers = User::query()->where('is_active', true)->orderBy('name')->get();

        return view('conferences.members', compact('conference', 'availableUsers'));
    }

    public function store(Request $request, Conference $conference, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manageMembers', $conference);
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::enum(ConferenceRole::class)],
        ]);
        $user = User::findOrFail($validated['user_id']);
        abort_unless($user->is_active, 422, 'Pengguna tidak aktif.');

        $membership = ConferenceMember::updateOrCreate(
            ['conference_id' => $conference->id, 'user_id' => $user->id, 'role' => $validated['role']],
            ['is_active' => true, 'added_by' => $request->user()->id],
        );
        $audit->record('conference.member_added', $conference, $conference, newValues: ['user_id' => $user->id, 'role' => $validated['role']]);

        return back()->with('success', "{$user->name} added as {$membership->role->label()}.");
    }

    public function destroy(Request $request, Conference $conference, ConferenceMember $member, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manageMembers', $conference);
        abort_unless($member->conference_id === $conference->id, 404);
        if ($member->user_id === $request->user()->id && $member->role === ConferenceRole::Admin) {
            return back()->withErrors(['member' => 'You cannot remove your own admin access.']);
        }

        $member->update(['is_active' => false]);
        $audit->record('conference.member_removed', $conference, $conference, oldValues: ['user_id' => $member->user_id, 'role' => $member->role->value]);

        return back()->with('success', 'Member access deactivated.');
    }
}
