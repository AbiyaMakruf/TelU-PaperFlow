<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('pdf_express_status')->nullable()->default('pending');
            $table->text('edas_error_note')->nullable();
            $table->string('revision_substatus')->nullable();
        });

        Schema::table('file_versions', function (Blueprint $table) {
            $table->string('file_category')->default('editable_manuscript');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['pdf_express_status', 'edas_error_note', 'revision_substatus']);
        });

        Schema::table('file_versions', function (Blueprint $table) {
            $table->dropColumn('file_category');
        });
    }
};
