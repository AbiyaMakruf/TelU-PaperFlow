<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $newBody = "Dear Authors,\n\nThank you for your submission {{paper_code}} titled \"{{paper_title}}\" to {{conference}}.\n\nOur team has reviewed your submission. Revision is required for your manuscript before proceeding to editorial review:\n\n<div style=\"margin: 20px 0; padding: 16px 20px; background-color: #fff1f2; border-left: 4px solid #e11d48; border-radius: 8px;\"><div style=\"font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #9f1239; margin-bottom: 6px;\">Editorial Feedback / Required Corrections</div><div style=\"color: #881337; font-size: 13.5px; line-height: 1.6;\">{{feedback}}</div></div>\n\n📌 <strong>IMPORTANT INSTRUCTIONS FOR REVISION:</strong>\n<ul style=\"margin: 10px 0 16px 0; padding-left: 20px; color: #334155; line-height: 1.65;\"><li style=\"margin-bottom: 10px; padding-left: 4px;\">Please download and use the <strong>LATEST MANUSCRIPT FILE</strong> available on your private Author Portal as the base for your revisions, as the editorial team may have already performed initial formatting corrections on it.</li><li style=\"margin-bottom: 10px; padding-left: 4px;\"><strong>ONLY REVISE THE SPECIFIC SECTIONS REQUESTED FOR CORRECTION.</strong> Please leave all other already compliant sections untouched.</li><li style=\"margin-bottom: 10px; padding-left: 4px;\">For full checklist details and to upload your revised file, please access your private Author Portal using the link below:</li></ul>\n\n{{portal_url}}\n\n<div style=\"margin-top: 10px; padding: 14px 18px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155;\"><strong style=\"color: #0f172a;\">Revision Deadline:</strong> {{deadline}}</div>\n\nBest regards,\n{{editor_name}}\n{{editor_job_title}}\n{{editor_affiliation}}";

        EmailTemplate::query()->where('key', 'revision_requested')->update([
            'body' => $newBody,
        ]);
    }

    public function down(): void
    {
        // No-op
    }
};
