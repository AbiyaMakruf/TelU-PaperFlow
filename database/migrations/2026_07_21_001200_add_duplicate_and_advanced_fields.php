<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_versions', function (Blueprint $table) {
            $table->string('file_hash')->nullable()->after('file_size');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->boolean('is_flagged_duplicate')->default(false)->after('status');
            $table->text('duplicate_notes')->nullable()->after('is_flagged_duplicate');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->text('reassignment_reason')->nullable()->after('note');
        });

        Schema::table('checklist_items', function (Blueprint $table) {
            $table->string('condition_type')->nullable()->after('is_required');
            $table->string('condition_value')->nullable()->after('condition_type');
        });
    }

    public function down(): void
    {
        Schema::table('file_versions', function (Blueprint $table) {
            $table->dropColumn('file_hash');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['is_flagged_duplicate', 'duplicate_notes']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('reassignment_reason');
        });

        Schema::table('checklist_items', function (Blueprint $table) {
            $table->dropColumn(['condition_type', 'condition_value']);
        });
    }
};
