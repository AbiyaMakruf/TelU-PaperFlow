<?php

namespace App\Services;

use App\Models\Conference;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GoogleDriveStorage
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive';

    public function redirectUri(): string
    {
        $custom = config('services.google_drive.redirect_uri');
        $uri = filled($custom)
            ? $custom
            : (function () {
                try {
                    return route('google-drive.callback');
                } catch (\Throwable) {
                    return url('/google-drive/callback');
                }
            })();

        if (str_starts_with((string) config('app.url'), 'https://') && str_starts_with($uri, 'http://')) {
            $uri = 'https://'.substr($uri, 7);
        }

        return $uri;
    }

    public function configured(): bool
    {
        return filled(config('services.google_drive.client_id'))
            && filled(config('services.google_drive.client_secret'))
            && filled($this->redirectUri());
    }

    public function connected(Conference $conference): bool
    {
        try {
            return $this->configured() && filled($conference->google_drive_folder_id)
                && is_array($conference->google_drive_token)
                && filled($conference->google_drive_token['refresh_token'] ?? $conference->google_drive_token['access_token'] ?? null);
        } catch (\Throwable) {
            return false;
        }
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
            'redirect_uri' => $this->redirectUri(),
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
            'redirect_uri' => $this->redirectUri(),
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

        try {
            $this->assertFolderWritable($conference);
            $name = preg_replace('/[^A-Za-z0-9._-]/', '-', $paperCode).'.'.strtolower($file->getClientOriginalExtension());
            $files = $this->client($conference)->get('https://www.googleapis.com/drive/v3/files', [
                'q' => sprintf("name = '%s' and '%s' in parents and trashed = false", $this->escapeQuery($name), $conference->google_drive_folder_id),
                'spaces' => 'drive', 'fields' => 'files(id,name,webViewLink)', 'pageSize' => 2,
                'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true',
            ])->throw()->json('files', []);

            if (count($files) > 1) {
                throw new RuntimeException("Lebih dari satu file Google Drive bernama {$name} ditemukan.");
            }

            $id = $files[0]['id'] ?? null;
            if (! $id) {
                $created = $this->client($conference)->post('https://www.googleapis.com/drive/v3/files?fields=id,name,webViewLink&supportsAllDrives=true', [
                    'name' => $name, 'parents' => [$conference->google_drive_folder_id],
                ])->throw()->json();
                $id = $created['id'];
            }

            $uploaded = $this->client($conference)
                ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream')
                ->patch("https://www.googleapis.com/upload/drive/v3/files/{$id}?uploadType=media&fields=id,name,webViewLink&supportsAllDrives=true")
                ->throw()->json();
        } catch (RequestException $exception) {
            if ($exception->response->status() === 403) {
                throw new RuntimeException('Akun Google yang terhubung tidak memiliki izin Editor untuk menambahkan file ke folder conference.', previous: $exception);
            }

            throw $exception;
        }

        return ['id' => $id, 'name' => $uploaded['name'] ?? $name,
            'webViewLink' => $uploaded['webViewLink'] ?? "https://drive.google.com/file/d/{$id}/view"];
    }

    public function download(Conference $conference, string $fileId, string $originalName): BinaryFileResponse
    {
        if (! $this->connected($conference)) {
            throw new RuntimeException('Google Drive conference belum terhubung.');
        }

        $directory = storage_path('app/private/google-drive-downloads');
        File::ensureDirectoryExists($directory);
        $temporaryPath = tempnam($directory, 'paperflow-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Gagal menyiapkan file sementara untuk download Google Drive.');
        }

        try {
            $response = $this->client($conference)
                ->withOptions(['sink' => $temporaryPath])
                ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", [
                    'alt' => 'media', 'supportsAllDrives' => 'true',
                ])->throw();

            // HTTP fakes do not write to Guzzle's sink, so retain their body for testability.
            if (filesize($temporaryPath) === 0 && $response->body() !== '') {
                file_put_contents($temporaryPath, $response->body());
            }

            return response()->download($temporaryPath, $originalName, [
                'Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            throw $exception;
        }
    }

    public function delete(Conference $conference, string $fileId): void
    {
        if ($this->connected($conference)) {
            $this->client($conference)->delete("https://www.googleapis.com/drive/v3/files/{$fileId}", ['supportsAllDrives' => 'true'])->throw();
        }
    }

    private function resolveFolderId(Conference $conference): string
    {
        $name = $this->folderName($conference);
        $files = $this->client($conference)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => sprintf("name = '%s' and mimeType = 'application/vnd.google-apps.folder' and trashed = false", $this->escapeQuery($name)),
            'spaces' => 'drive', 'fields' => 'files(id,name,capabilities(canAddChildren))', 'pageSize' => 10,
            'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true',
        ])->throw()->json('files', []);
        if (count($files) !== 1) {
            throw new RuntimeException(count($files) === 0
                ? "Folder Google Drive '{$name}' tidak ditemukan."
                : "Ditemukan lebih dari satu folder Google Drive bernama '{$name}'.");
        }
        if (! ($files[0]['capabilities']['canAddChildren'] ?? false)) {
            throw new RuntimeException("Akun Google tidak memiliki izin Editor pada folder '{$name}'.");
        }

        return $files[0]['id'];
    }

    private function assertFolderWritable(Conference $conference): void
    {
        $folder = $this->client($conference)->get("https://www.googleapis.com/drive/v3/files/{$conference->google_drive_folder_id}", [
            'fields' => 'id,name,capabilities(canAddChildren)', 'supportsAllDrives' => 'true',
        ])->throw()->json();

        if (! ($folder['capabilities']['canAddChildren'] ?? false)) {
            throw new RuntimeException('Akun Google yang terhubung tidak memiliki izin Editor pada folder conference.');
        }
    }

    private function client(Conference $conference): PendingRequest
    {
        try {
            $token = $conference->google_drive_token ?? [];
        } catch (\Throwable $e) {
            throw new RuntimeException('Token Google Drive terenkripsi tidak dapat didekripsi karena APP_KEY berbeda. Harap hubungkan ulang akun Google Drive.', previous: $e);
        }
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
