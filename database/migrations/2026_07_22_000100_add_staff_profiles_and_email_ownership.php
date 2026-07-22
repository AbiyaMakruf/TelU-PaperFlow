<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_country_code', 8)->nullable();
            $table->string('whatsapp_number', 32)->nullable();
            $table->string('job_title')->nullable();
            $table->string('affiliation')->nullable();
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('body')->nullable();
            $table->index(['conference_id', 'status', 'created_at']);
            $table->index(['sender_user_id', 'status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE email_logs ENABLE ROW LEVEL SECURITY');
        }

        $template = <<<'EMAIL'
Dear Authors,

First of all, congratulations on the acceptance of your paper for {{conference}}.

Before we proceed with the final manuscript upload, the {{conference}} Publication Committee has reviewed your submission for template compliance. We identified several discrepancies regarding the official IEEE formatting. Although these details may not be flagged by PDF eXpress, they are required to maintain the quality standards of the conference proceedings.

The editor has already performed direct corrections within your source files. As certain sections can only be revised by the authors, we are returning the updated files for final adjustments. You are required to use the latest Word/LaTeX source files from Paperflow as the base for your final revision. Do not use previous versions because they do not contain the editor's recent improvements.

Remaining corrections:
{{feedback}}

Supporting documents may include:
- Revised Source File (Word/LaTeX): the latest version containing the editor's initial corrections.
- Revision List: the comprehensive list of additional changes to address.
- Formatting Guidance (PDF): detailed instructions and visual examples for specific revisions.

Submission Instructions: upload your revised editable files through the private Paperflow author portal:
{{portal_url}}

Deadline: {{deadline}}

Contact and Support: reply to this email or contact the editor via WhatsApp:
{{editor_whatsapp_url}}

Thank you for your cooperation and prompt attention to these requirements.

Best regards,
{{editor_name}}
{{editor_job_title}}
{{editor_affiliation}}
Publication Committee {{conference}}
EMAIL;

        DB::table('email_templates')->where('key', 'revision_requested')->update([
            'subject' => '[{{conference}}] Required Final Revision for {{paper_code}}',
            'body' => $template,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['conference_id', 'status', 'created_at']);
            $table->dropIndex(['sender_user_id', 'status', 'created_at']);
            $table->dropConstrainedForeignId('sender_user_id');
            $table->dropColumn('body');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_country_code', 'whatsapp_number', 'job_title', 'affiliation']);
        });
    }
};
