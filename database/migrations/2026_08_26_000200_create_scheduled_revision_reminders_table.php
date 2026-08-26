<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_revision_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conference_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('email_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind')->index();
            $table->string('recipient')->nullable();
            $table->date('deadline_date')->index();
            $table->timestampTz('scheduled_for')->index();
            $table->string('status')->default('scheduled')->index();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_revision_reminders');
    }
};
