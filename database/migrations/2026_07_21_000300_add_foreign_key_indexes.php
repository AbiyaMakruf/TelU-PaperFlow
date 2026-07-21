<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $indexes = [
        'conferences' => ['created_by'],
        'conference_members' => ['user_id', 'added_by'],
        'form_versions' => ['created_by'],
        'checklist_templates' => ['conference_id'],
        'checklist_items' => ['checklist_template_id'],
        'submissions' => ['form_version_id', 'editor_id', 'reviewer_id'],
        'submission_authors' => ['submission_id'],
        'assignments' => ['user_id', 'assigned_by'],
        'file_versions' => ['uploaded_by'],
        'review_cycles' => ['checklist_template_id', 'assigned_to'],
        'review_item_results' => ['checklist_item_id', 'checked_by'],
        'feedback' => ['submission_id', 'review_cycle_id', 'created_by'],
        'status_history' => ['changed_by'],
        'email_logs' => ['conference_id', 'submission_id'],
        'audit_logs' => ['user_id', 'conference_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
