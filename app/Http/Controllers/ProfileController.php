<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user(), 'countryCodes' => config('country-codes')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'whatsapp_country_code' => ['nullable', Rule::in(array_keys(config('country-codes')))],
            'whatsapp_number' => ['nullable', 'string', 'max:32', 'regex:/^[0-9\s().-]+$/'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'affiliation' => ['nullable', 'string', 'max:255'],
        ]);
        $user->update($validated);

        return back()->with('success', 'Profil berhasil diperbarui. Identitas ini digunakan pada komunikasi editorial.');
    }
}
