<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserManualController extends Controller
{
    /**
     * Public user manual for Authors.
     */
    public function author()
    {
        return view('user-manual.author');
    }

    /**
     * Authenticated User Manual Hub / Role Selector.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $primaryRole = $this->determinePrimaryRole($user);

        return redirect()->route('user-manual.show', ['role' => $primaryRole]);
    }

    /**
     * Show role-specific user manual.
     */
    public function show(Request $request, string $role)
    {
        $validRoles = ['author', 'superadmin', 'admin', 'editorial', 'reviewer', 'viewer'];

        if (! in_array($role, $validRoles, true)) {
            abort(404, 'User manual role tidak ditemukan.');
        }

        // Public Author manual can be viewed by anyone
        if ($role === 'author') {
            return view('user-manual.author');
        }

        // Staff manuals require authentication
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu untuk mengakses user manual staf.');
        }

        $user = $request->user();

        // Validate role access hierarchy
        if (! $this->canAccessRoleManual($user, $role)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membaca user manual role '.$role.'.');
        }

        return view('user-manual.'.$role, [
            'activeRole' => $role,
            'userPrimaryRole' => $this->determinePrimaryRole($user),
        ]);
    }

    /**
     * Determine user's highest active role.
     */
    private function determinePrimaryRole($user): string
    {
        if ($user->isSuperAdmin()) {
            return 'superadmin';
        }

        $memberships = $user->conferenceMemberships()
            ->where('is_active', true)
            ->get();

        if ($memberships->contains('role', ConferenceRole::Admin)) {
            return 'admin';
        }

        if ($memberships->contains('role', ConferenceRole::Editorial)) {
            return 'editorial';
        }

        if ($memberships->contains('role', ConferenceRole::Reviewer)) {
            return 'reviewer';
        }

        if ($memberships->contains('role', ConferenceRole::Viewer)) {
            return 'viewer';
        }

        return 'author';
    }

    /**
     * Check if user can access specific role manual.
     */
    private function canAccessRoleManual($user, string $targetRole): bool
    {
        if ($targetRole === 'author') {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($targetRole === 'superadmin') {
            return false;
        }

        // Any authenticated staff member can view staff manuals to understand the role ecosystem
        return $user->conferenceMemberships()->where('is_active', true)->exists();
    }
}
