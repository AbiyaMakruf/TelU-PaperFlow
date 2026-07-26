<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (EmailTemplate::query()->where('key', 'revision_requested')->get() as $template) {
            $body = (string) $template->body;
            $body = str_replace(['Revision Revision Deadline:', 'Revision Revision'], ['Revision Deadline:', 'Revision'], $body);
            $body = preg_replace('/<strong>\s*Revision\s*<strong>\s*Revision\s*Deadline:\s*\{\{deadline\}\}\s*<\/strong>\s*<\/strong>/i', '<strong>Revision Deadline: {{deadline}}</strong>', $body);
            $body = preg_replace('/<strong>\s*Revision\s*Deadline:\s*\{\{deadline\}\}\s*<\/strong>/i', '___REVISION_DEADLINE_PLACEHOLDER___', $body);
            $body = preg_replace('/(Revision\s+)?Deadline:\s*\{\{deadline\}\}/i', '<strong>Revision Deadline: {{deadline}}</strong>', $body);
            $body = str_replace('___REVISION_DEADLINE_PLACEHOLDER___', '<strong>Revision Deadline: {{deadline}}</strong>', $body);

            $template->update(['body' => $body]);
        }
    }

    public function down(): void
    {
        // No-op
    }
};
