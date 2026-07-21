<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conferences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('submission_opens_at')->nullable();
            $table->timestamp('submission_closes_at')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('conference_members', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conference_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['conference_id', 'user_id', 'role']);
        });

        Schema::create('form_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conference_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('draft')->index();
            $table->json('schema');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['conference_id', 'version']);
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conference_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('stage')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conference_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('form_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('paper_code')->nullable();
            $table->string('title');
            $table->string('corresponding_author_name');
            $table->string('corresponding_author_email')->index();
            $table->string('corresponding_author_phone')->nullable();
            $table->json('answers')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_token_hash', 64)->nullable()->index();
            $table->timestamp('author_token_expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('edas_reference')->nullable();
            $table->text('edas_notes')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['conference_id', 'paper_code']);
            $table->index(['conference_id', 'status']);
        });

        Schema::create('submission_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('submission_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('affiliation')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_corresponding')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();
            $table->index(['submission_id', 'role', 'unassigned_at']);
        });

        Schema::create('file_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('label');
            $table->string('source')->index();
            $table->string('disk')->default('supabase');
            $table->text('storage_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_final')->default(false)->index();
            $table->timestamps();
            $table->unique(['submission_id', 'version_number']);
        });

        Schema::create('review_cycles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('checklist_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage')->index();
            $table->unsignedInteger('cycle_number');
            $table->string('status')->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['submission_id', 'stage', 'cycle_number']);
        });

        Schema::create('review_item_results', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('review_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_checked')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['review_cycle_id', 'checklist_item_id']);
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('review_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visibility')->default('internal')->index();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('status_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status')->index();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['submission_id', 'created_at']);
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conference_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('subject');
            $table->text('body');
            $table->json('default_cc')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['conference_id', 'key']);
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conference_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_key')->nullable();
            $table->string('recipient');
            $table->json('cc')->nullable();
            $table->string('subject');
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('conference_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->nullableUlidMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('status_history');
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('review_item_results');
        Schema::dropIfExists('review_cycles');
        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('submission_authors');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('conference_members');
        Schema::dropIfExists('conferences');
    }
};
