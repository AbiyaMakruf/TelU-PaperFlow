<?php

namespace App\Services;

use App\Models\Conference;
use App\Models\FileVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConferenceFileStorage
{
    public function __construct(
        private readonly PrivateFileStorage $privateStorage,
        private readonly GoogleDriveStorage $googleDrive,
    ) {}

    public function ready(Conference $conference): bool
    {
        return ! $conference->usesGoogleDrive() || $this->googleDrive->connected($conference);
    }

    /** @return array{disk:string,storage_path:string,external_provider:?string,external_id:?string,external_url:?string} */
    public function put(Conference $conference, UploadedFile $file, string $path, string $driveName): array
    {
        if ($conference->usesGoogleDrive()) {
            $uploaded = $this->googleDrive->upload($conference, $file, $driveName);

            return [
                'disk' => 'google_drive',
                'storage_path' => $uploaded['id'],
                'external_provider' => 'google_drive',
                'external_id' => $uploaded['id'],
                'external_url' => $uploaded['webViewLink'],
            ];
        }

        $this->privateStorage->put($file, $path);

        return [
            'disk' => $this->privateStorage->usesSupabase() ? 'supabase' : 'local',
            'storage_path' => $path,
            'external_provider' => null,
            'external_id' => null,
            'external_url' => null,
        ];
    }

    public function download(FileVersion $file): RedirectResponse|BinaryFileResponse
    {
        $externalUrl = $file->external_url ?: (str_starts_with((string) $file->storage_path, 'http') ? $file->storage_path : null);

        if ($file->disk === 'google_drive') {
            $file->loadMissing('submission.conference');
            $conference = $file->submission?->conference;

            if ($conference && $file->external_id && $this->googleDrive->connected($conference)) {
                try {
                    return $this->googleDrive->download(
                        $conference,
                        $file->external_id,
                        $file->original_name,
                    );
                } catch (\Throwable $e) {
                    if ($externalUrl) {
                        return redirect()->away($externalUrl);
                    }
                    throw $e;
                }
            }

            if ($externalUrl) {
                return redirect()->away($externalUrl);
            }

            abort_unless($conference, 404);

            return $this->googleDrive->download(
                $conference,
                $file->external_id ?: $file->storage_path,
                $file->original_name,
            );
        }

        if ($externalUrl) {
            return redirect()->away($externalUrl);
        }

        if ($url = $this->privateStorage->temporaryUrl($file->storage_path, 300, $file->original_name)) {
            return redirect()->away($url);
        }

        return response()->download($this->privateStorage->localPath($file->storage_path), $file->original_name);
    }

    /** @return array{path:string,cleanup:bool} */
    public function temporaryCopy(FileVersion $file): array
    {
        if ($file->disk === 'local') {
            return ['path' => $this->privateStorage->localPath($file->storage_path), 'cleanup' => false];
        }

        $externalUrl = $file->external_url ?: (str_starts_with((string) $file->storage_path, 'http') ? $file->storage_path : null);

        if ($file->disk === 'google_drive') {
            $file->loadMissing('submission.conference');
            $conference = $file->submission?->conference;

            if ($conference && $file->external_id && $this->googleDrive->connected($conference)) {
                try {
                    $response = $this->googleDrive->download($conference, $file->external_id, $file->original_name);

                    return ['path' => $response->getFile()->getPathname(), 'cleanup' => true];
                } catch (\Throwable $e) {
                    if (! $externalUrl) {
                        throw $e;
                    }
                }
            }

            if ($externalUrl) {
                $directory = storage_path('app/private/previews');
                File::ensureDirectoryExists($directory);
                $path = tempnam($directory, 'preview-');
                Http::withOptions(['sink' => $path])->get($externalUrl)->throw();

                return ['path' => $path, 'cleanup' => true];
            }

            $response = $this->googleDrive->download($file->submission->conference, $file->external_id ?: $file->storage_path, $file->original_name);

            return ['path' => $response->getFile()->getPathname(), 'cleanup' => true];
        }

        if ($externalUrl) {
            $directory = storage_path('app/private/previews');
            File::ensureDirectoryExists($directory);
            $path = tempnam($directory, 'preview-');
            Http::withOptions(['sink' => $path])->get($externalUrl)->throw();

            return ['path' => $path, 'cleanup' => true];
        }

        $url = $this->privateStorage->temporaryUrl($file->storage_path) ?: throw new \RuntimeException('Signed URL file tidak tersedia.');
        $directory = storage_path('app/private/previews');
        File::ensureDirectoryExists($directory);
        $path = tempnam($directory, 'preview-');
        Http::withOptions(['sink' => $path])->get($url)->throw();

        return ['path' => $path, 'cleanup' => true];
    }

    public function migrateStorage(Conference $conference, string $targetProvider): int
    {
        $files = FileVersion::query()
            ->whereHas('submission', fn ($q) => $q->where('conference_id', $conference->id))
            ->get();

        $migratedCount = 0;
        $originalProvider = $conference->storage_provider;
        $conference->update(['storage_provider' => $targetProvider]);

        foreach ($files as $file) {
            if ($file->disk === $targetProvider) {
                continue;
            }
            try {
                $temp = $this->temporaryCopy($file);
                $uploadedFile = new UploadedFile($temp['path'], $file->original_name, $file->mime_type, null, true);
                $paperCode = $file->submission ? $file->submission->paper_code : 'FILE-'.$file->id;
                $newPath = $conference->slug.'/'.$file->submission_id.'/v'.$file->version_number.'-migrated-'.Str::slug(pathinfo($file->original_name, PATHINFO_FILENAME)).'.'.pathinfo($file->original_name, PATHINFO_EXTENSION);

                $stored = $this->put($conference, $uploadedFile, $newPath, $paperCode.'-V'.$file->version_number);

                $file->update([
                    'disk' => $stored['disk'],
                    'storage_path' => $stored['storage_path'],
                    'external_provider' => $stored['external_provider'],
                    'external_id' => $stored['external_id'],
                    'external_url' => $stored['external_url'],
                ]);

                if ($temp['cleanup'] && is_file($temp['path'])) {
                    @unlink($temp['path']);
                }
                $migratedCount++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $migratedCount;
    }

    public function deleteConferenceFiles(Conference $conference): void
    {
        $files = FileVersion::query()
            ->whereHas('submission', fn ($q) => $q->where('conference_id', $conference->id))
            ->get();

        foreach ($files as $file) {
            try {
                if ($file->disk === 'supabase' || $file->disk === 'local') {
                    $this->privateStorage->delete($file->storage_path);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        try {
            $this->privateStorage->deleteDirectory($conference->slug);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
