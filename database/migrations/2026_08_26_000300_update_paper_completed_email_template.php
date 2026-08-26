<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $body = "Dear {{author_name}},\n\nWe are pleased to confirm that your manuscript has met the editorial and technical requirements. Our editorial team has completed the IEEE PDF eXpress process and uploaded the final manuscript to EDAS on your behalf.\n\n<div style=\"margin:20px 0;padding:18px 20px;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;\"><div style=\"font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#64748b;margin-bottom:12px;\">Final Manuscript Details</div><table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;font-size:13.5px;color:#334155;line-height:1.6;\"><tr><td style=\"padding:6px 0;font-weight:700;color:#0f172a;width:120px;vertical-align:top;\">Paper ID</td><td style=\"padding:6px 0;color:#f47c20;font-weight:800;font-size:14px;vertical-align:top;\">{{paper_code}}</td></tr><tr><td style=\"padding:6px 0;font-weight:700;color:#0f172a;vertical-align:top;\">Paper Title</td><td style=\"padding:6px 0;color:#1e293b;font-weight:600;vertical-align:top;\">{{paper_title}}</td></tr></table></div>\n\nPlease review the final manuscript in EDAS to confirm that it is correct. If you find any discrepancy, contact your assigned PIC using the contact details available in your Author Portal.\n\n<div style=\"margin:20px 0;text-align:center;\"><a href=\"{{portal_url}}\" style=\"display:inline-block;background:#f47c20;color:#ffffff;text-decoration:none;font-size:14px;font-weight:800;padding:12px 26px;border-radius:8px;box-shadow:0 3px 10px rgba(244,124,32,0.25);\">Open Author Portal</a></div>\n\nBest regards,\nEditorial Team\n{{conference}}";

        EmailTemplate::query()->where('key', 'paper_completed')->update(['body' => $body]);
    }

    public function down(): void
    {
        // No-op: preserve the current template on rollback.
    }
};
