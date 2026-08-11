<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->withCount('conferenceMemberships')
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($scope) => $scope
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->merge(['username' => Str::lower(trim($request->string('username')->toString()))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash:ascii', 'unique:users,username'],
            'is_super_admin' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => Str::lower($validated['username']),
            'email' => null,
            'is_super_admin' => $request->boolean('is_super_admin'),
            'password' => Hash::make('user1234'),
            'must_change_password' => true,
            'is_active' => true,
        ]);
        $audit->record('user.created', $user, newValues: ['username' => $user->username, 'is_super_admin' => $user->is_super_admin]);

        return redirect()->route('admin.users.index')->with('success', 'User account created. Initial temporary password is user1234.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $request->merge([
            'username' => Str::lower(trim($request->string('username')->toString())),
            'email' => $request->filled('email') ? Str::lower(trim($request->string('email')->toString())) : null,
        ]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash:ascii', Rule::unique('users')->ignore($user)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'is_active' => ['nullable', 'boolean'],
            'is_super_admin' => ['nullable', 'boolean'],
        ]);

        if ($request->user()->is($user) && (! $request->boolean('is_active') || ! $request->boolean('is_super_admin'))) {
            return back()->withErrors(['is_active' => 'You cannot deactivate or revoke your own superadmin access.']);
        }

        $old = $user->only(['name', 'username', 'email', 'is_active', 'is_super_admin']);
        $user->update([
            'name' => $validated['name'],
            'username' => Str::lower($validated['username']),
            'email' => filled($validated['email'] ?? null) ? Str::lower($validated['email']) : null,
            'is_active' => $request->boolean('is_active'),
            'is_super_admin' => $request->boolean('is_super_admin'),
        ]);
        $audit->record('user.updated', $user, oldValues: $old, newValues: $user->only(array_keys($old)));

        return redirect()->route('admin.users.index')->with('success', 'User account updated successfully.');
    }

    public function resetPassword(User $user, AuditLogger $audit): RedirectResponse
    {
        $user->update([
            'password' => Hash::make('user1234'),
            'must_change_password' => true,
        ]);
        $audit->record('user.password_reset_to_default', $user, newValues: ['must_change_password' => true]);

        return back()->with('success', 'Password reset to user1234. User will be prompted to change password on next login.');
    }
}
