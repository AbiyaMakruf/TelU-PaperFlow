<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conferences', function (Blueprint $table) {
            $table->text('google_drive_token')->nullable();
            $table->string('google_drive_folder_id')->nullable();
            $table->timestampTz('google_drive_connected_at')->nullable();
        });
        Schema::table('file_versions', function (Blueprint $table) {
            $table->string('external_provider')->nullable();
            $table->string('external_id')->nullable();
            $table->text('external_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('file_versions', fn (Blueprint $table) => $table->dropColumn(['external_provider', 'external_id', 'external_url']));
        Schema::table('conferences', fn (Blueprint $table) => $table->dropColumn(['google_drive_token', 'google_drive_folder_id', 'google_drive_connected_at']));
    }
};
