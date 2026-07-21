<?php

namespace App\Services;

use App\Models\Conference;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveStorage
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive';

    public function configured(): bool
    {
        return filled(config('services.google_drive.client_id'))
            && filled(config('services.google_drive.client_secret'))
            && filled(config('services.google_drive.redirect_uri'));
    }

    public function connected(Conference $conference): bool
    {
        return $this->configured() && filled($conference->google_drive_folder_id)
            && is_array($conference->google_drive_token)
            && filled($conference->google_drive_token['refresh_token'] ?? $conference->google_drive_token['access_token'] ?? null);
    }

    public function folderName(Conference $conference): string
    {
        return strtr((string) config('services.google_drive.folder_name', '{conference}'), [
            '{conference}' => $conference->name,
            '{slug}' => $conference->slug,
        ]);
    }

    public function authorizationUrl(string $state): string
    {
        $this->ensureConfigured();

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_drive.client_id'),
            'redirect_uri' => config('services.google_drive.redirect_uri'),
            'response_type' => 'code', 'scope' => self::SCOPE, 'access_type' => 'offline',
            'prompt' => 'consent', 'state' => $state,
        ]);
    }

    public function connect(Conference $conference, string $code): void
    {
        $this->ensureConfigured();
        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => config('services.google_drive.redirect_uri'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();
        $token['expires_at'] = now()->addSeconds(max(60, ((int) ($token['expires_in'] ?? 3600)) - 60))->timestamp;
        $conference->update(['google_drive_token' => $token]);

        $conference->update([
            'google_drive_folder_id' => $this->resolveFolderId($conference->fresh()),
            'google_drive_connected_at' => now(),
        ]);
    }

    public function disconnect(Conference $conference): void
    {
        $conference->update(['google_drive_token' => null, 'google_drive_folder_id' => null, 'google_drive_connected_at' => null]);
    }

    /** @return array{id:string,name:string,webViewLink:string} */
    public function upload(Conference $conference, UploadedFile $file, string $paperCode): array
    {
        if (! $this->connected($conference)) {
            throw new RuntimeException('Google Drive conference belum terhubung.');
        }

        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', $paperCode).'.'.strtolower($file->getClientOriginalExtension());
        $files = $this->client($conference)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => sprintf("name = '%s' and '%s' in parents and trashed = false", $this->escapeQuery($name), $conference->google_drive_folder_id),
            'spaces' => 'drive', 'fields' => 'files(id,name,webViewLink)', 'pageSize' => 2,
        ])->throw()->json('files', []);

        if (count($files) > 1) {
            throw new RuntimeException("Lebih dari satu file Google Drive bernama {$name} ditemukan.");
        }

        $id = $files[0]['id'] ?? null;
        if (! $id) {
            $created = $this->client($conference)->post('https://www.googleapis.com/drive/v3/files?fields=id,name,webViewLink', [
                'name' => $name, 'parents' => [$conference->google_drive_folder_id],
            ])->throw()->json();
            $id = $created['id'];
        }

        $uploaded = $this->client($conference)
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream')
            ->patch("https://www.googleapis.com/upload/drive/v3/files/{$id}?uploadType=media&fields=id,name,webViewLink")
            ->throw()->json();

        return ['id' => $id, 'name' => $uploaded['name'] ?? $name,
            'webViewLink' => $uploaded['webViewLink'] ?? "https://drive.google.com/file/d/{$id}/view"];
    }

    private function resolveFolderId(Conference $conference): string
    {
        $name = $this->folderName($conference);
        $files = $this->client($conference)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => sprintf("name = '%s' and mimeType = 'application/vnd.google-apps.folder' and trashed = false", $this->escapeQuery($name)),
            'spaces' => 'drive', 'fields' => 'files(id,name)', 'pageSize' => 10,
        ])->throw()->json('files', []);
        if (count($files) !== 1) {
            throw new RuntimeException(count($files) === 0
                ? "Folder Google Drive '{$name}' tidak ditemukan."
                : "Ditemukan lebih dari satu folder Google Drive bernama '{$name}'.");
        }

        return $files[0]['id'];
    }

    private function client(Conference $conference): PendingRequest
    {
        $token = $conference->google_drive_token ?? [];
        if (($token['expires_at'] ?? 0) <= now()->timestamp) {
            if (blank($token['refresh_token'] ?? null)) {
                throw new RuntimeException('Sesi Google Drive kedaluwarsa. Hubungkan ulang akun.');
            }
            $fresh = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google_drive.client_id'),
                'client_secret' => config('services.google_drive.client_secret'),
                'refresh_token' => $token['refresh_token'], 'grant_type' => 'refresh_token',
            ])->throw()->json();
            $token = array_merge($token, $fresh, ['expires_at' => now()->addSeconds(max(60, ((int) ($fresh['expires_in'] ?? 3600)) - 60))->timestamp]);
            $conference->update(['google_drive_token' => $token]);
        }

        return Http::withToken($token['access_token'])->acceptJson();
    }

    private function escapeQuery(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, dan GOOGLE_REDIRECT_URI belum lengkap.');
        }
    }
}
