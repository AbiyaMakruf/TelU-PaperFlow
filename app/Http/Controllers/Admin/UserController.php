<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
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
                ->where(fn ($scope) => $scope->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'is_super_admin' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            ...$validated,
            'is_super_admin' => $request->boolean('is_super_admin'),
            'password' => Str::password(32),
            'must_change_password' => true,
            'is_active' => true,
        ]);
        Password::sendResetLink(['email' => $user->email]);
        $audit->record('user.created', $user, newValues: ['email' => $user->email, 'is_super_admin' => $user->is_super_admin]);

        return redirect()->route('admin.users.index')->with('success', 'Akun dibuat dan tautan aktivasi dikirim.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'is_active' => ['nullable', 'boolean'],
            'is_super_admin' => ['nullable', 'boolean'],
        ]);

        if ($request->user()->is($user) && (! $request->boolean('is_active') || ! $request->boolean('is_super_admin'))) {
            return back()->withErrors(['is_active' => 'Anda tidak dapat menonaktifkan atau mencabut akses superadmin sendiri.']);
        }

        $old = $user->only(['name', 'email', 'is_active', 'is_super_admin']);
        $user->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'is_super_admin' => $request->boolean('is_super_admin'),
        ]);
        $audit->record('user.updated', $user, oldValues: $old, newValues: $user->only(array_keys($old)));

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil diperbarui.');
    }

    public function resendActivation(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', 'Tautan aktivasi/reset telah dikirim ulang.');
    }
}
