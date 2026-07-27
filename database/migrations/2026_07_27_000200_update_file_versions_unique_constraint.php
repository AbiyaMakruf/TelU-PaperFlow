<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_versions', function (Blueprint $table) {
            $table->dropUnique('file_versions_submission_id_version_number_unique');
            $table->unique(['submission_id', 'version_number', 'file_category']);
        });
    }

    public function down(): void
    {
        Schema::table('file_versions', function (Blueprint $table) {
            $table->dropUnique(['submission_id', 'version_number', 'file_category']);
            $table->unique(['submission_id', 'version_number']);
        });
    }
};
