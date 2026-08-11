<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedInteger('initial_page_count')->nullable()->after('manuscript_format');
            $table->unsignedInteger('final_page_count')->nullable()->after('initial_page_count');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['initial_page_count', 'final_page_count']);
        });
    }
};
