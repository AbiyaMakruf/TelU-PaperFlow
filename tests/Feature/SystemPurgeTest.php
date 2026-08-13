<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_or_non_superadmin_cannot_execute_system_purge(): void
    {
        $editor = User::factory()->create([
            'is_super_admin' => false,
            'password' => Hash::make('user1234'),
        ]);

        $this->post(route('admin.system.purge'), ['password' => 'user1234'])
            ->assertRedirect(route('login'));

        $this->actingAs($editor)
            ->post(route('admin.system.purge'), ['password' => 'user1234'])
            ->assertForbidden();
    }

    public function test_superadmin_with_invalid_password_cannot_purge_system(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'password' => Hash::make('superpassword123'),
        ]);

        $conference = Conference::create([
            'name' => 'Test Conference',
            'slug' => 'test-conf',
            'description' => 'Conference description',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.system.purge'), ['password' => 'wrongpassword'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('conferences', ['id' => $conference->id]);
    }

    public function test_superadmin_with_valid_password_purges_all_data_except_superadmin(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'password' => Hash::make('superpassword123'),
        ]);

        $regularUser = User::factory()->create([
            'is_super_admin' => false,
        ]);

        $conference = Conference::create([
            'name' => 'Purge Test Conference',
            'slug' => 'purge-conf',
            'description' => 'To be purged',
        ]);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_code' => 'PAPER-101',
            'title' => 'Sample Submission Paper',
            'corresponding_author_name' => 'Author Name',
            'corresponding_author_email' => 'author@example.com',
            'submitted_at' => now(),
        ]);

        // Upload a file to storage
        $fileVersion = $submission->files()->create([
            'version_number' => 1,
            'label' => 'Initial Manuscript',
            'source' => 'author',
            'original_name' => 'manuscript.docx',
            'file_category' => 'editable_manuscript',
            'disk' => 'private',
            'storage_path' => 'submissions/'.$submission->id.'/manuscript.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 500 * 1024,
            'checksum' => md5('test'),
        ]);
        Storage::disk('private')->put('submissions/'.$submission->id.'/manuscript.docx', 'dummy content');

        $this->assertDatabaseHas('conferences', ['id' => $conference->id]);
        $this->assertDatabaseHas('users', ['id' => $regularUser->id]);
        $this->assertTrue(Storage::disk('private')->exists('submissions/'.$submission->id.'/manuscript.docx'));

        // Execute Purge
        $response = $this->actingAs($superAdmin)->post(route('admin.system.purge'), [
            'password' => 'superpassword123',
        ]);

        $response->assertRedirect(route('admin.monitoring.index', ['tab' => 'system']));
        $response->assertSessionHas('status');

        // Assert database is clean except superadmin
        $this->assertDatabaseMissing('conferences', ['id' => $conference->id]);
        $this->assertDatabaseMissing('users', ['id' => $regularUser->id]);
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
        $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
        $this->assertDatabaseMissing('file_versions', ['id' => $fileVersion->id]);

        // Assert physical storage files are wiped
        $this->assertFalse(Storage::disk('private')->exists('submissions/'.$submission->id.'/manuscript.docx'));
    }
}
