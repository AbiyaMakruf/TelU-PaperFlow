<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Services\GoogleDriveStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class GoogleDriveController extends Controller
{
    public function show(Conference $conference, GoogleDriveStorage $drive): View
    {
        $this->authorize('update', $conference);

        return view('conferences.google-drive', compact('conference', 'drive'));
    }

    public function connect(Request $request, Conference $conference, GoogleDriveStorage $drive): RedirectResponse
    {
        $this->authorize('update', $conference);
        if (! $drive->configured()) {
            return back()->withErrors(['google_drive' => 'Konfigurasi Google OAuth di .env belum lengkap.']);
        }

        $state = Str::random(64);
        $request->session()->put('google_drive_oauth', ['state' => $state, 'conference_id' => $conference->id]);

        return redirect()->away($drive->authorizationUrl($state));
    }

    public function updateProvider(Request $request, Conference $conference, GoogleDriveStorage $drive): RedirectResponse
    {
        $this->authorize('update', $conference);
        $validated = $request->validate([
            'storage_provider' => ['required', Rule::in(['supabase', 'google_drive'])],
        ]);
        if ($validated['storage_provider'] === 'google_drive' && ! $drive->connected($conference)) {
            return back()->withErrors(['storage_provider' => 'Hubungkan Google Drive sebelum memilihnya sebagai penyimpanan.']);
        }
        $conference->update($validated);

        return back()->with('success', 'Penyimpanan default conference berhasil diperbarui.');
    }

    public function callback(Request $request, GoogleDriveStorage $drive): RedirectResponse
    {
        $oauth = $request->session()->pull('google_drive_oauth');
        abort_unless($oauth && hash_equals($oauth['state'], (string) $request->query('state')), 403, 'State OAuth tidak valid.');
        $conference = Conference::findOrFail($oauth['conference_id']);
        $this->authorize('update', $conference);

        if ($request->filled('error')) {
            return redirect()->route('conferences.drive.show', $conference)->withErrors(['google_drive' => 'Akses Google Drive dibatalkan.']);
        }
        $request->validate(['code' => ['required', 'string']]);

        try {
            $drive->connect($conference, $request->string('code')->toString());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('conferences.drive.show', $conference)->withErrors(['google_drive' => $exception->getMessage()]);
        }

        return redirect()->route('conferences.drive.show', $conference)->with('success', 'Google Drive berhasil dihubungkan.');
    }

    public function disconnect(Conference $conference, GoogleDriveStorage $drive): RedirectResponse
    {
        $this->authorize('update', $conference);
        $drive->disconnect($conference);

        return back()->with('success', 'Koneksi Google Drive dilepas dari conference.');
    }

    public function migrateStorage(Request $request, Conference $conference, \App\Services\ConferenceFileStorage $storage): RedirectResponse
    {
        $this->authorize('update', $conference);
        $validated = $request->validate([
            'target_provider' => ['required', Rule::in(['supabase', 'google_drive'])],
        ]);

        $count = $storage->migrateStorage($conference, $validated['target_provider']);

        return back()->with('success', "Proses migrasi selesai. {$count} berkas berhasil dipindahkan ke {$validated['target_provider']}.");
    }
}
