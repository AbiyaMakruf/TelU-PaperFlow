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
            $table->string('original_paper_code', 60)->nullable()->after('paper_code')->index();
            $table->string('original_title', 500)->nullable()->after('title');
            $table->string('original_author_email', 255)->nullable()->after('corresponding_author_email');
        });

        // Backfill original fields for existing submissions
        try {
            DB::statement('UPDATE submissions SET original_paper_code = paper_code WHERE original_paper_code IS NULL AND paper_code IS NOT NULL');
            DB::statement('UPDATE submissions SET original_title = title WHERE original_title IS NULL AND title IS NOT NULL');
            DB::statement('UPDATE submissions SET original_author_email = corresponding_author_email WHERE original_author_email IS NULL AND corresponding_author_email IS NOT NULL');
        } catch (Throwable) {
            // Ignore backfill errors on empty test tables
        }
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['original_paper_code', 'original_title', 'original_author_email']);
        });
    }
};
