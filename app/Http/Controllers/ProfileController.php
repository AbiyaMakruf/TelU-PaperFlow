<?php

namespace App\Http\Controllers;

use App\Mail\PaperflowMail;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'countryCodes' => config('country-codes'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'whatsapp_country_code' => ['nullable', Rule::in(array_keys(config('country-codes')))],
            'whatsapp_number' => ['nullable', 'string', 'max:32', 'regex:/^[0-9\s().-]+$/'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'affiliation' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($validated);

        app(AuditLogger::class)->record(
            event: 'profile.updated',
            auditable: $user,
            newValues: $validated
        );

        return back()->with('success', 'Profile updated successfully. This identity is used in editorial communications.');
    }

    public function checkUsername(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/'],
        ]);

        $newUsername = trim((string) $request->username);
        $currentUser = $request->user();

        if (strtolower($newUsername) === strtolower((string) $currentUser->username)) {
            return response()->json([
                'available' => true,
                'is_current' => true,
                'message' => 'This is your current username.',
            ]);
        }

        $exists = User::whereRaw('LOWER(username) = ?', [strtolower($newUsername)])
            ->where('id', '!=', $currentUser->id)
            ->exists();

        return response()->json([
            'available' => ! $exists,
            'is_current' => false,
            'message' => $exists ? 'Username is already taken by another user.' : 'Username is available!',
        ]);
    }

    public function updateUsername(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-zA-Z0-9_.-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
        ]);

        $oldUsername = $user->username;
        $user->update(['username' => $validated['username']]);

        app(AuditLogger::class)->record(
            event: 'profile.username_updated',
            auditable: $user,
            oldValues: ['username' => $oldUsername],
            newValues: ['username' => $validated['username']]
        );

        return back()->with('success', 'Username updated successfully to "'.$validated['username'].'". Please use this new username for your next login.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['old_password'], $user->password)) {
            return back()->withErrors(['old_password' => 'The provided current password does not match.']);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        app(AuditLogger::class)->record(
            event: 'profile.password_updated',
            auditable: $user
        );

        return back()->with('success', 'Your password has been updated successfully.');
    }

    public function requestEmailOtp(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'new_email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $otp = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(15);

        session([
            'email_change_otp' => [
                'new_email' => $validated['new_email'],
                'code' => $otp,
                'expires_at' => $expiresAt->timestamp,
            ],
        ]);

        // Build English Email Message
        $subject = 'Paperflow - Email Verification Code';
        $messageBody = "Hello {$user->name},\n\n"
            ."You have requested to change your account email address on Paperflow to {$validated['new_email']}.\n"
            .'Please enter the verification code below into your Paperflow profile page to complete your email change.';

        try {
            Mail::to($validated['new_email'])->send(new PaperflowMail(
                mailSubject: $subject,
                messageBody: $messageBody,
                senderName: 'Paperflow System',
                contextName: 'Security Verification',
                otpCode: $otp,
            ));
        } catch (\Throwable $e) {
            logger()->error('Failed to send email verification OTP: '.$e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'A 4-digit OTP code has been sent to '.$validated['new_email'],
                'email' => $validated['new_email'],
            ]);
        }

        return back()->with('otp_sent', true)
            ->with('pending_new_email', $validated['new_email'])
            ->with('success', 'A 4-digit OTP code has been sent to '.$validated['new_email'].'. Please check your inbox.');
    }

    public function verifyEmailOtp(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:4'],
        ]);

        $sessionData = session('email_change_otp');
        if (! $sessionData || empty($sessionData['code'])) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'OTP request not found or expired. Please request a new OTP code.'], 422);
            }

            return back()->withErrors(['otp' => 'OTP request not found or expired. Please request a new OTP code.']);
        }

        if (now()->timestamp > $sessionData['expires_at']) {
            session()->forget('email_change_otp');
            if ($request->wantsJson()) {
                return response()->json(['message' => 'OTP code has expired. Please request a new OTP code.'], 422);
            }

            return back()->withErrors(['otp' => 'OTP code has expired. Please request a new OTP code.']);
        }

        if (! hash_equals((string) $sessionData['code'], trim((string) $validated['otp']))) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid 4-digit OTP code.'], 422);
            }

            return back()->withErrors(['otp' => 'Invalid 4-digit OTP code.']);
        }

        $user = $request->user();
        $oldEmail = $user->email;
        $newEmail = $sessionData['new_email'];

        $user->update(['email' => $newEmail]);
        session()->forget('email_change_otp');

        app(AuditLogger::class)->record(
            event: 'profile.email_updated',
            auditable: $user,
            oldValues: ['email' => $oldEmail],
            newValues: ['email' => $newEmail]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Your email address has been updated successfully to '.$newEmail,
                'email' => $newEmail,
            ]);
        }

        return back()->with('success', 'Your email address has been updated successfully to '.$newEmail);
    }
}
