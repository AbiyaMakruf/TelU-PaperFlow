<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        EmailTemplate::query()->where('key', 'revision_requested')->update([
            'subject' => '[{{conference}}] Revision Required for Submission {{paper_code}}',
            'body' => "Dear Authors,\n\nThank you for your submission {{paper_code}} to {{conference}}.\n\nOur team has reviewed your submission. Action or revision is required based on the following feedback:\n\n{{feedback}}\n\nPlease submit your revision or update your details through your private portal:\n{{portal_url}}\n\nDeadline: {{deadline}}\n\nFor clarification, contact the team via WhatsApp: {{editor_whatsapp_url}}\n\nBest regards,\n{{editor_name}}\n{{editor_job_title}}\n{{editor_affiliation}}\n{{conference}} Committee",
        ]);
    }

    public function down(): void
    {
        // No-op
    }
};
