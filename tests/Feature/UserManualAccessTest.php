<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Models\Conference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManualAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_author_manual_is_accessible_without_login(): void
    {
        $response = $this->get(route('user-manual.author'));

        $response->assertOk();
        $response->assertSee('Author User Manual');
        $response->assertSee('Public Submission Form');
    }

    public function test_guest_cannot_access_staff_user_manuals(): void
    {
        $response = $this->get(route('user-manual.show', 'editorial'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_staff_user_can_access_user_manual_hub_and_role_manuals(): void
    {
        $user = User::factory()->create();
        $conference = Conference::create([
            'name' => 'IEEE Test Conference',
            'slug' => 'ieee-test',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'storage_provider' => 'supabase',
        ]);

        $conference->memberships()->create([
            'user_id' => $user->id,
            'role' => ConferenceRole::Editorial,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('user-manual.index'));
        $response->assertRedirect(route('user-manual.show', 'editorial'));

        $showResponse = $this->actingAs($user)->get(route('user-manual.show', 'editorial'));
        $showResponse->assertOk();
        $showResponse->assertSee('User Manual: Editorial');
        $showResponse->assertSee('16 Item IEEE Compliance Checklist');

        $reviewerResponse = $this->actingAs($user)->get(route('user-manual.show', 'reviewer'));
        $reviewerResponse->assertOk();
        $reviewerResponse->assertSee('User Manual: Reviewer');
    }

    public function test_superadmin_can_access_all_role_manuals(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);

        foreach (['superadmin', 'admin', 'editorial', 'reviewer', 'viewer', 'author'] as $role) {
            $response = $this->actingAs($superadmin)->get(route('user-manual.show', $role));
            $response->assertOk();
        }
    }
}
