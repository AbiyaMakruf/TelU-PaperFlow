<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        EmailTemplate::query()->where('key', 'submission_received')->update([
            'subject' => '[{{conference}}] Submission {{paper_code}} Received',
            'body' => "Dear {{author_name}},\n\nThank you for your submission {{paper_code}} to {{conference}}. We have successfully received your paper.\n\nYou can track the progress of your paper and manage your submission via your private author portal:\n{{portal_url}}\n\nBest regards,\nEditorial Team\n{{conference}}",
        ]);
    }

    public function down(): void
    {
        // No-op
    }
};
