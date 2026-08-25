<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PrivateFileStorage
{
    public function put(UploadedFile $file, string $path): void
    {
        if (! $this->usesSupabase()) {
            $stream = fopen($file->getRealPath(), 'r');
            Storage::disk('local')->put($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return;
        }

        $stream = fopen($file->getRealPath(), 'r');
        $response = Http::withToken($this->secret())
            ->withHeaders(['apikey' => $this->secret(), 'x-upsert' => 'false'])
            ->withBody($stream, $file->getMimeType() ?: 'application/octet-stream')
            ->post($this->objectUrl($path));

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengunggah file ke penyimpanan privat: '.$response->body());
        }
    }

    public function putFromLocalPath(string $localFilePath, string $destinationPath, ?string $mimeType = null): void
    {
        $stream = fopen($localFilePath, 'r');
        $response = Http::withToken($this->secret())
            ->withHeaders(['apikey' => $this->secret(), 'x-upsert' => 'true'])
            ->withBody($stream, $mimeType ?: 'application/octet-stream')
            ->post($this->objectUrl($destinationPath));

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengunggah file ke penyimpanan Supabase: '.$response->body());
        }
    }

    public function checkBucket(): array
    {
        try {
            $response = Http::withToken($this->secret())
                ->withHeaders(['apikey' => $this->secret()])
                ->get($this->storageUrl('/bucket/'.$this->bucket()));

            if ($response->failed()) {
                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ];
            }

            return [
                'ok' => true,
                'data' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function temporaryUrl(string $path, int $expiresIn = 300, ?string $downloadName = null): ?string
    {
        if (! $this->usesSupabase()) {
            return null;
        }

        $payload = ['expiresIn' => $expiresIn];
        if ($downloadName) {
            $payload['download'] = $downloadName;
        }

        $response = Http::withToken($this->secret())
            ->withHeaders(['apikey' => $this->secret()])
            ->post($this->storageUrl('/object/sign/'.$this->bucket().'/'.$this->encodePath($path)), $payload)
            ->throw();

        $signedUrl = $response->json('signedURL') ?? $response->json('signedUrl');
        if (! is_string($signedUrl)) {
            throw new RuntimeException('Supabase tidak mengembalikan signed URL.');
        }

        if (str_starts_with($signedUrl, 'http')) {
            return $signedUrl;
        }

        return str_starts_with($signedUrl, '/storage/v1/')
            ? rtrim($this->baseUrl(), '/').$signedUrl
            : $this->storageUrl('/'.ltrim($signedUrl, '/'));
    }

    public function delete(string $path): void
    {
        if (! $this->usesSupabase()) {
            Storage::disk('local')->delete($path);

            return;
        }

        Http::withToken($this->secret())
            ->withHeaders(['apikey' => $this->secret()])
            ->delete($this->objectUrl($path));
    }

    public function deleteDirectory(string $folderPath): void
    {
        if (! $this->usesSupabase()) {
            Storage::disk('local')->deleteDirectory($folderPath);

            return;
        }

        Http::withToken($this->secret())
            ->withHeaders(['apikey' => $this->secret()])
            ->delete($this->storageUrl('/object/'.$this->bucket()), [
                'prefixes' => [trim($folderPath, '/')],
            ]);
    }

    public function localPath(string $path): string
    {
        return Storage::disk('local')->path($path);
    }

    public function usesSupabase(): bool
    {
        return config('services.supabase.storage_driver') === 'supabase';
    }

    private function objectUrl(string $path): string
    {
        return $this->storageUrl('/object/'.$this->bucket().'/'.$this->encodePath($path));
    }

    private function storageUrl(string $path): string
    {
        return rtrim($this->baseUrl(), '/').'/storage/v1'.$path;
    }

    private function baseUrl(): string
    {
        return (string) config('services.supabase.url');
    }

    private function bucket(): string
    {
        return (string) config('services.supabase.storage_bucket');
    }

    private function secret(): string
    {
        $secret = (string) config('services.supabase.secret_key');
        if ($secret === '') {
            throw new RuntimeException('SUPABASE_SECRET_KEY belum dikonfigurasi.');
        }

        return $secret;
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));
    }
}
