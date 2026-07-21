<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestampTz('deadline_at')->nullable()->index();
            $table->timestampTz('edas_submitted_at')->nullable();
            $table->foreignId('edas_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('edas_approved_at')->nullable();
            $table->foreignId('edas_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('upload_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source');
            $table->string('label');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->text('temporary_path');
            $table->text('notes')->nullable();
            $table->boolean('is_final')->default(false);
            $table->string('status')->default('failed')->index();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestampTz('retried_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE upload_attempts ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_attempts');
        Schema::dropIfExists('notifications');
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edas_approved_by');
            $table->dropConstrainedForeignId('edas_submitted_by');
            $table->dropColumn(['deadline_at', 'edas_submitted_at', 'edas_approved_at']);
        });
    }
};
