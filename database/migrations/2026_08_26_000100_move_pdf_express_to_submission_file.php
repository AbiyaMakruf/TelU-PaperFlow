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
            $table->string('pdf_express_disk')->nullable()->after('pdf_express_status');
            $table->text('pdf_express_storage_path')->nullable();
            $table->string('pdf_express_original_name')->nullable();
            $table->string('pdf_express_mime_type')->nullable();
            $table->unsignedBigInteger('pdf_express_size')->nullable();
            $table->string('pdf_express_checksum', 64)->nullable();
            $table->string('pdf_express_external_provider')->nullable();
            $table->string('pdf_express_external_id')->nullable();
            $table->text('pdf_express_external_url')->nullable();
            $table->timestamp('pdf_express_uploaded_at')->nullable();
            $table->foreignUlid('pdf_express_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('edas_warnings')->nullable();
        });

        DB::table('submissions')->orderBy('id')->each(function (object $submission): void {
            $file = DB::table('file_versions')->where('submission_id', $submission->id)
                ->where('file_category', 'camera_ready_pdf')->whereNull('deleted_at')
                ->orderByDesc('is_final')->orderByDesc('version_number')->orderByDesc('created_at')->first();
            if (! $file) {
                return;
            }
            DB::table('submissions')->where('id', $submission->id)->update([
                'pdf_express_disk' => $file->disk, 'pdf_express_storage_path' => $file->storage_path,
                'pdf_express_original_name' => $file->original_name, 'pdf_express_mime_type' => $file->mime_type,
                'pdf_express_size' => $file->size, 'pdf_express_checksum' => $file->checksum,
                'pdf_express_external_provider' => $file->external_provider, 'pdf_express_external_id' => $file->external_id,
                'pdf_express_external_url' => $file->external_url, 'pdf_express_uploaded_at' => $file->created_at,
                'pdf_express_uploaded_by' => $file->uploaded_by,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pdf_express_uploaded_by');
            $table->dropColumn(['pdf_express_disk', 'pdf_express_storage_path', 'pdf_express_original_name', 'pdf_express_mime_type', 'pdf_express_size', 'pdf_express_checksum', 'pdf_express_external_provider', 'pdf_express_external_id', 'pdf_express_external_url', 'pdf_express_uploaded_at', 'edas_warnings']);
        });
    }
};
