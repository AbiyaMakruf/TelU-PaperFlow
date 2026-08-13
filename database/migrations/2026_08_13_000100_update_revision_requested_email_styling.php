<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $newBody = "Dear Authors,\n\nThank you for your submission {{paper_code}} titled \"{{paper_title}}\" to {{conference}}.\n\nOur team has reviewed your submission. Revision is required for your manuscript before proceeding to editorial review:\n\n{{feedback}}\n\n📌 <strong>IMPORTANT INSTRUCTIONS FOR REVISION:</strong>\n<ul style=\"margin: 12px 0 20px 0; padding-left: 24px; color: #334155; line-height: 1.65;\"><li style=\"margin-bottom: 10px; padding-left: 6px;\">Please download and use the <strong>LATEST MANUSCRIPT FILE</strong> available on your private Author Portal as the base for your revisions, as the editorial team may have already performed initial formatting corrections on it.</li><li style=\"margin-bottom: 10px; padding-left: 6px;\"><strong>ONLY REVISE THE SPECIFIC SECTIONS REQUESTED FOR CORRECTION.</strong> Please leave all other already compliant sections untouched.</li><li style=\"margin-bottom: 10px; padding-left: 6px;\">For full checklist details and to upload your revised file, please access your private Author Portal using the button below:</li></ul>\n\n<div style=\"margin: 20px 0; text-align: center;\"><a href=\"{{portal_url}}\" style=\"display: inline-block; background: #f47c20; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 800; padding: 12px 26px; border-radius: 8px; box-shadow: 0 3px 10px rgba(244,124,32,0.25);\">Open Portal &amp; Upload Revision</a></div>\n\n<div style=\"margin-top: 16px; padding: 14px 18px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155;\"><strong style=\"color: #0f172a;\">Revision Deadline:</strong> {{deadline}}</div>\n\nBest regards,\n{{editor_name}}\n{{editor_job_title}}\n{{editor_affiliation}}";

        EmailTemplate::query()->where('key', 'revision_requested')->update([
            'body' => $newBody,
        ]);
    }

    public function down(): void
    {
        // No-op
    }
};
