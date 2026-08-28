<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_versions', function (Blueprint $table) {
            $table->foreignUlid('based_on_file_version_id')
                ->nullable()
                ->after('submission_id')
                ->constrained('file_versions')
                ->nullOnDelete();
        });

        Schema::table('upload_attempts', function (Blueprint $table) {
            $table->foreignUlid('based_on_file_version_id')
                ->nullable()
                ->after('submission_id')
                ->constrained('file_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('upload_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('based_on_file_version_id');
        });

        Schema::table('file_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('based_on_file_version_id');
        });
    }
};
