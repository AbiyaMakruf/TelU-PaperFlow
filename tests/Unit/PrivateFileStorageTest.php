<?php

namespace Tests\Unit;

use App\Services\PrivateFileStorage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrivateFileStorageTest extends TestCase
{
    public function test_relative_supabase_signed_url_receives_storage_api_prefix(): void
    {
        config()->set('services.supabase', [
            'url' => 'https://project.supabase.co',
            'secret_key' => 'server-secret',
            'storage_bucket' => 'paperflow-private',
            'storage_driver' => 'supabase',
        ]);
        Http::fake([
            'https://project.supabase.co/storage/v1/object/sign/*' => Http::response([
                'signedURL' => '/object/sign/paperflow-private/conf/paper.docx?token=abc',
            ]),
        ]);

        $url = app(PrivateFileStorage::class)->temporaryUrl('conf/paper.docx');

        $this->assertSame('https://project.supabase.co/storage/v1/object/sign/paperflow-private/conf/paper.docx?token=abc', $url);
    }
}
