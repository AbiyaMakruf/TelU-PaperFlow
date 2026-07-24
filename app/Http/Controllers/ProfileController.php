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

        return back()->with('success', 'Profil berhasil diperbarui. Identitas ini digunakan pada komunikasi editorial.');
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
                'message' => 'Ini adalah username Anda saat ini.',
            ]);
        }

        $exists = User::whereRaw('LOWER(username) = ?', [strtolower($newUsername)])
            ->where('id', '!=', $currentUser->id)
            ->exists();

        return response()->json([
            'available' => ! $exists,
            'is_current' => false,
            'message' => $exists ? 'Username sudah digunakan oleh pengguna lain.' : 'Username tersedia!',
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

        return back()->with('success', 'Username berhasil diperbarui menjadi "'.$validated['username'].'". Gunakan username ini untuk login berikutnya.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['old_password'], $user->password)) {
            return back()->withErrors(['old_password' => 'Password saat ini yang Anda masukkan tidak sesuai.']);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        app(AuditLogger::class)->record(
            event: 'profile.password_updated',
            auditable: $user
        );

        return back()->with('success', 'Password Anda berhasil diperbarui.');
    }

    public function requestEmailOtp(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'new_email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Password saat ini tidak sesuai.'], 422);
            }

            return back()->withErrors(['password' => 'Password saat ini tidak sesuai.']);
        }

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
            ."You have requested to change your account email address on Paperflow to {$validated['new_email']}.\n\n"
            ."Your 4-digit email verification code is:\n\n"
            ."      {$otp}\n\n"
            ."This code will expire in 15 minutes. If you did not request this change, please ignore this email.\n\n"
            ."Best regards,\nPaperflow Editorial Team";

        try {
            Mail::to($validated['new_email'])->send(new PaperflowMail(
                mailSubject: $subject,
                messageBody: $messageBody,
                senderName: 'Paperflow System',
                contextName: 'Security Verification',
            ));
        } catch (\Throwable $e) {
            // Log mail exception if mailer service is unavailable in local env
            logger()->error('Failed to send email verification OTP: '.$e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Kode OTP 4 digit telah dikirimkan ke '.$validated['new_email'],
                'email' => $validated['new_email'],
            ]);
        }

        return back()->with('otp_sent', true)
            ->with('pending_new_email', $validated['new_email'])
            ->with('success', 'Kode OTP 4 digit telah dikirimkan ke email '.$validated['new_email'].'. Silakan periksa kotak masuk Anda.');
    }

    public function verifyEmailOtp(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:4'],
        ]);

        $sessionData = session('email_change_otp');
        if (! $sessionData || empty($sessionData['code'])) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Permintaan OTP tidak ditemukan atau sudah kadaluarsa. Silakan minta kode OTP baru.'], 422);
            }

            return back()->withErrors(['otp' => 'Permintaan OTP tidak ditemukan atau sudah kadaluarsa. Silakan minta kode OTP baru.']);
        }

        if (now()->timestamp > $sessionData['expires_at']) {
            session()->forget('email_change_otp');
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Kode OTP sudah kadaluarsa. Silakan minta kode OTP baru.'], 422);
            }

            return back()->withErrors(['otp' => 'Kode OTP sudah kadaluarsa. Silakan minta kode OTP baru.']);
        }

        if (! hash_equals((string) $sessionData['code'], trim((string) $validated['otp']))) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Kode OTP 4-digit yang Anda masukkan salah.'], 422);
            }

            return back()->withErrors(['otp' => 'Kode OTP 4-digit yang Anda masukkan salah.']);
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
                'message' => 'Alamat email berhasil diperbarui menjadi '.$newEmail,
                'email' => $newEmail,
            ]);
        }

        return back()->with('success', 'Alamat email Anda berhasil diperbarui menjadi '.$newEmail);
    }
}
