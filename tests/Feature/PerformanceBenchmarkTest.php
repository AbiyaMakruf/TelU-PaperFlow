<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_paper_list_executes_minimal_queries_under_high_volume_without_n_plus_one(): void
    {
        $conf = Conference::create(['name' => 'High Volume Conf', 'slug' => 'hv-conf', 'status' => 'active']);
        $admin = User::factory()->create();
        $conf->memberships()->create(['user_id' => $admin->id, 'role' => ConferenceRole::Admin, 'is_active' => true]);

        // Seed 100 submissions with authors and files
        for ($i = 0; $i < 100; $i++) {
            $sub = Submission::create([
                'id' => (string) Str::ulid(),
                'conference_id' => $conf->id,
                'paper_id' => 'PERF-'.$i,
                'paper_code' => 'HV-PERF-'.$i,
                'title' => 'Performance Paper Title '.$i,
                'corresponding_author_name' => 'Author '.$i,
                'corresponding_author_email' => "author{$i}@example.com",
                'corresponding_author_phone' => '+62812345678',
                'status' => SubmissionStatus::Submitted,
                'submitted_at' => now(),
            ]);
            $sub->authors()->create(['name' => 'Author '.$i, 'email' => "author{$i}@example.com", 'is_corresponding' => true, 'sort_order' => 1]);
        }

        DB::enableQueryLog();

        $startTime = microtime(true);
        $response = $this->actingAs($admin)
            ->withSession(['active_conference_id' => $conf->id])
            ->get(route('submissions.index'));
        $durationMs = (microtime(true) - $startTime) * 1000;

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertOk();
        $this->assertLessThanOrEqual(25, $queryCount, "Paper list should use eager loading and stay under 25 queries for 100 papers. Actual queries: {$queryCount}");
        $this->assertLessThan(2000, $durationMs, "Page render time should be under 2000ms for 100 items in test environment. Actual duration: {$durationMs}ms");
    }

    public function test_author_portal_response_time_and_query_efficiency(): void
    {
        $conf = Conference::create(['name' => 'Portal Conf', 'slug' => 'portal-conf', 'status' => 'active']);
        $token = Str::random(64);

        $sub = Submission::create([
            'id' => (string) Str::ulid(),
            'conference_id' => $conf->id,
            'paper_id' => 'PORTAL-1',
            'paper_code' => 'PORTAL-1',
            'title' => 'Portal Performance Test Paper',
            'corresponding_author_name' => 'Portal Author',
            'corresponding_author_email' => 'portal@example.com',
            'corresponding_author_phone' => '+62812345678',
            'status' => SubmissionStatus::EditorialReview,
            'author_token_hash' => hash('sha256', $token),
            'author_token_encrypted' => $token,
            'author_token_expires_at' => now()->addYear(),
            'submitted_at' => now(),
        ]);

        DB::enableQueryLog();
        $startTime = microtime(true);

        $response = $this->get(route('author.portal', $token));

        $durationMs = (microtime(true) - $startTime) * 1000;
        $queryCount = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertLessThanOrEqual(15, $queryCount, "Author portal query count should be under 15. Actual: {$queryCount}");
        $this->assertLessThan(2000, $durationMs, "Author portal load time should be under 2000ms in test environment. Actual: {$durationMs}ms");
    }
}
