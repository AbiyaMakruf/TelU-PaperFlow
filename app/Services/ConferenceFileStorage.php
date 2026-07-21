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
        if ($file->disk === 'google_drive') {
            $file->loadMissing('submission.conference');
            abort_unless($file->submission?->conference, 404);

            return $this->googleDrive->download(
                $file->submission->conference,
                $file->external_id ?: $file->storage_path,
                $file->original_name,
            );
        }

        if ($url = $this->privateStorage->temporaryUrl($file->storage_path)) {
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
        if ($file->disk === 'google_drive') {
            $file->loadMissing('submission.conference');
            $response = $this->googleDrive->download($file->submission->conference, $file->external_id ?: $file->storage_path, $file->original_name);

            return ['path' => $response->getFile()->getPathname(), 'cleanup' => true];
        }
        $url = $this->privateStorage->temporaryUrl($file->storage_path) ?: throw new \RuntimeException('Signed URL file tidak tersedia.');
        $directory = storage_path('app/private/previews');
        File::ensureDirectoryExists($directory);
        $path = tempnam($directory, 'preview-');
        Http::withOptions(['sink' => $path])->get($url)->throw();

        return ['path' => $path, 'cleanup' => true];
    }
}
