<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs',
        'job_batches', 'failed_jobs', 'migrations', 'conferences', 'conference_members', 'form_versions',
        'checklist_templates', 'checklist_items', 'submissions', 'submission_authors',
        'assignments', 'file_versions', 'review_cycles', 'review_item_results', 'feedback',
        'status_history', 'email_templates', 'email_logs', 'audit_logs',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE \"{$table}\" FORCE ROW LEVEL SECURITY");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE \"{$table}\" NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE \"{$table}\" DISABLE ROW LEVEL SECURITY");
        }
    }
};
