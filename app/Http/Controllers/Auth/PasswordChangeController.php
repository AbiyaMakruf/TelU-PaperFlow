<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password', [
            'countryCodes' => config('country-codes'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim($request->string('email')->toString()))]);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user())],
            'whatsapp_country_code' => ['required', 'string', 'max:10'],
            'whatsapp_number' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $cleanPhone = preg_replace('/\D+/', '', $validated['whatsapp_number']);

        $request->user()->update([
            'email' => Str::lower($validated['email']),
            'email_verified_at' => null,
            'whatsapp_country_code' => $validated['whatsapp_country_code'],
            'whatsapp_number' => $cleanPhone,
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Profile and account password updated successfully.');
    }
}
