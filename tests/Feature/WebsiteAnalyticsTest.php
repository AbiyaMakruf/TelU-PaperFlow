<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Models\ConferenceMember;
use App\Models\User;
use App\Models\WebsitePageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function createConference(array $overrides = []): Conference
    {
        return Conference::create(array_merge([
            'name' => 'ICoSEIT 2026',
            'slug' => 'icoseit-2026',
            'status' => ConferenceStatus::Active,
            'submission_mode' => 'paperflow_native',
            'starts_at' => now(),
            'ends_at' => now()->addDays(3),
            'submission_opens_at' => now()->subDays(10),
            'submission_closes_at' => now()->addDays(10),
        ], $overrides));
    }

    protected function createSuperAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'must_change_password' => false,
            'email' => 'superadmin@example.com',
        ]);
    }

    protected function createStaff(string $role, Conference $conference): User
    {
        $user = User::factory()->create([
            'is_super_admin' => false,
            'must_change_password' => false,
        ]);

        ConferenceMember::create([
            'conference_id' => $conference->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }

    public function test_guest_and_non_superadmin_cannot_access_analytics(): void
    {
        $conference = $this->createConference();

        // 1. Guest redirected to login
        $this->get(route('admin.analytics.index'))->assertRedirect(route('login'));

        // 2. Conference Admin forbidden
        $confAdmin = $this->createStaff(ConferenceRole::Admin->value, $conference);
        $this->actingAs($confAdmin)->get(route('admin.analytics.index'))->assertForbidden();

        // 3. Editorial forbidden
        $editor = $this->createStaff(ConferenceRole::Editorial->value, $conference);
        $this->actingAs($editor)->get(route('admin.analytics.index'))->assertForbidden();

        // 4. Reviewer forbidden
        $reviewer = $this->createStaff(ConferenceRole::Reviewer->value, $conference);
        $this->actingAs($reviewer)->get(route('admin.analytics.index'))->assertForbidden();

        // 5. Viewer forbidden
        $viewer = $this->createStaff(ConferenceRole::Viewer->value, $conference);
        $this->actingAs($viewer)->get(route('admin.analytics.index'))->assertForbidden();
    }

    public function test_superadmin_can_access_analytics_dashboard(): void
    {
        $superadmin = $this->createSuperAdmin();
        $conference = $this->createConference();

        // Seed sample pageviews
        WebsitePageView::create([
            'conference_id' => $conference->id,
            'visitor_hash' => hash('sha256', 'visitor-1'),
            'path' => '/' . $conference->slug,
            'page_type' => 'landing',
            'method' => 'GET',
            'status_code' => 200,
            'referrer' => 'https://google.com',
            'referrer_type' => 'search',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'platform' => 'macOS',
            'visited_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertSee('Website Analytics');
        $response->assertSee('Total Pageviews');
        $response->assertSee('Unique Visitors');
        $response->assertSee('Submission Conversion Funnel');
        $response->assertSee('Device Breakdown');
        $response->assertSee('ICoSEIT 2026');
    }

    public function test_track_website_pageview_middleware_records_anonymized_pageview(): void
    {
        $conference = $this->createConference();

        $response = $this->get(route('public.conference.show', $conference->slug), [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer' => 'https://google.com/search?q=icoseit',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('website_page_views', [
            'conference_id' => $conference->id,
            'page_type' => 'landing',
            'method' => 'GET',
            'status_code' => 200,
            'referrer_type' => 'search',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'platform' => 'macOS',
        ]);

        $pageview = WebsitePageView::where('page_type', 'landing')->first();
        $this->assertNotNull($pageview);
        $this->assertEquals(64, strlen($pageview->visitor_hash)); // SHA256 length
    }

    public function test_track_website_pageview_masks_portal_tokens(): void
    {
        $fakeToken = 'secret-token-abcdef123456';

        // Even on 404/invalid token, pageview middleware runs terminate()
        $this->get("/submission/access/{$fakeToken}");

        $this->assertDatabaseHas('website_page_views', [
            'path' => '/submission/access/:token',
            'page_type' => 'portal',
        ]);

        // Ensure real token was NOT stored
        $this->assertDatabaseMissing('website_page_views', [
            'path' => "/submission/access/{$fakeToken}",
        ]);
    }

    public function test_track_website_pageview_ignores_assets_and_bots(): void
    {
        $initialCount = WebsitePageView::count();

        // 1. Asset request ignored
        $this->get('/build/app.css');
        $this->assertEquals($initialCount, WebsitePageView::count());

        // 2. Health check ignored
        $this->get('/up');
        $this->assertEquals($initialCount, WebsitePageView::count());

        // 3. Crawler Bot ignored
        $this->get('/', [
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ]);
        $this->assertEquals($initialCount, WebsitePageView::count());
    }

    public function test_superadmin_can_filter_analytics_by_conference_and_period(): void
    {
        $superadmin = $this->createSuperAdmin();
        $conference1 = $this->createConference(['slug' => 'conf-1', 'name' => 'Conf 1']);
        $conference2 = $this->createConference(['slug' => 'conf-2', 'name' => 'Conf 2']);

        WebsitePageView::create([
            'conference_id' => $conference1->id,
            'visitor_hash' => hash('sha256', 'v1'),
            'path' => '/conf-1',
            'page_type' => 'landing',
            'method' => 'GET',
            'status_code' => 200,
            'device_type' => 'mobile',
            'visited_at' => now()->subDays(2),
        ]);

        WebsitePageView::create([
            'conference_id' => $conference2->id,
            'visitor_hash' => hash('sha256', 'v2'),
            'path' => '/conf-2',
            'page_type' => 'landing',
            'method' => 'GET',
            'status_code' => 200,
            'device_type' => 'desktop',
            'visited_at' => now()->subDays(2),
        ]);

        // Filter for conf-1 only
        $response = $this->actingAs($superadmin)->get(route('admin.analytics.index', [
            'conference_id' => $conference1->id,
            'period' => '7d',
        ]));

        $response->assertOk();
        $response->assertViewHas('totalPageviews', 1);
        $response->assertViewHas('selectedConferenceId', $conference1->id);
    }

    public function test_superadmin_can_export_analytics_csv(): void
    {
        $superadmin = $this->createSuperAdmin();
        $conference = $this->createConference();

        WebsitePageView::create([
            'conference_id' => $conference->id,
            'visitor_hash' => hash('sha256', 'v1'),
            'path' => '/' . $conference->slug,
            'page_type' => 'landing',
            'method' => 'GET',
            'status_code' => 200,
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'visited_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('admin.analytics.export'));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Timestamp (WIB)', $response->streamedContent());
        $this->assertStringContainsString($conference->name, $response->streamedContent());
    }
}
