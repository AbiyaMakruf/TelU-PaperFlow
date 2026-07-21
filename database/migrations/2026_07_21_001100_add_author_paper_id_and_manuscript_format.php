<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('paper_id')->nullable()->after('form_version_id');
            $table->string('manuscript_format')->nullable()->after('paper_code');
            $table->unique(['conference_id', 'paper_id']);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropUnique(['conference_id', 'paper_id']);
            $table->dropColumn(['paper_id', 'manuscript_format']);
        });
    }
};
