<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function impersonate(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $currentUser = $request->user();
        abort_unless($currentUser->isSuperAdmin(), 403, 'Hanya superadmin yang dapat melakukan impersonation.');
        abort_if($currentUser->id === $user->id, 400, 'Anda tidak dapat mengimpersonasi diri sendiri.');

        $originalUserId = $currentUser->id;
        Auth::login($user);
        $request->session()->put('impersonated_by', $originalUserId);

        $audit->record('user.impersonated', $user, newValues: ['impersonated_by' => $originalUserId, 'target_user' => $user->id]);

        return redirect()->route('dashboard')->with('success', "You are now impersonating {$user->name} ({$user->username}).");
    }

    public function leave(Request $request, AuditLogger $audit): RedirectResponse
    {
        $originalUserId = $request->session()->pull('impersonated_by');
        abort_unless($originalUserId, 400, 'Impersonation session is not active.');

        $originalUser = User::findOrFail($originalUserId);
        $impersonatedUser = $request->user();

        Auth::login($originalUser);

        $audit->record('user.impersonated_leave', $originalUser, newValues: ['impersonated_user' => $impersonatedUser->id]);

        return redirect()->route('admin.users.index')->with('success', 'Returned to Superadmin account.');
    }
}
