<?php

namespace App\Services;

use App\Enums\ConferenceRole;
use App\Enums\ReviewStage;
use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConferenceProvisioner
{
    public function create(array $attributes, User $creator): Conference
    {
        return DB::transaction(function () use ($attributes, $creator) {
            $conference = Conference::create([...$attributes, 'created_by' => $creator->id]);
            $conference->memberships()->create([
                'user_id' => $creator->id,
                'role' => ConferenceRole::Admin,
                'is_active' => true,
                'added_by' => $creator->id,
            ]);
            $this->createDefaults($conference, $creator);

            return $conference;
        });
    }

    public function duplicate(Conference $source, array $attributes, User $creator): Conference
    {
        return DB::transaction(function () use ($source, $attributes, $creator) {
            $conference = Conference::create([
                ...$source->only(['description', 'timezone', 'settings']),
                ...$attributes,
                'status' => 'draft',
                'created_by' => $creator->id,
            ]);
            $conference->memberships()->create([
                'user_id' => $creator->id,
                'role' => ConferenceRole::Admin,
                'is_active' => true,
                'added_by' => $creator->id,
            ]);

            $form = $source->formVersions()->latest('version')->first();
            FormVersion::create([
                'conference_id' => $conference->id,
                'version' => 1,
                'status' => 'draft',
                'schema' => $form?->schema ?? $this->defaultFormSchema(),
                'created_by' => $creator->id,
            ]);

            foreach ($source->checklistTemplates()->with('items')->get() as $template) {
                $copy = $conference->checklistTemplates()->create($template->only(['name', 'stage', 'is_active']));
                foreach ($template->items as $item) {
                    $copy->items()->create($item->only(['title', 'description', 'is_required', 'sort_order']));
                }
            }
            foreach ($source->emailTemplates as $template) {
                $conference->emailTemplates()->create($template->only(['key', 'subject', 'body', 'default_cc', 'is_enabled']));
            }

            return $conference;
        });
    }

    private function createDefaults(Conference $conference, User $creator): void
    {
        FormVersion::create([
            'conference_id' => $conference->id,
            'version' => 1,
            'status' => 'draft',
            'schema' => $this->defaultFormSchema(),
            'created_by' => $creator->id,
        ]);

        $editorial = $conference->checklistTemplates()->create([
            'name' => 'Editorial Compliance Check (IEEE Standard)',
            'stage' => ReviewStage::Editorial,
        ]);
        $ieeeItems = [
            ['title' => 'Template', 'description' => 'Must use IEEE\'s latex/word template (https://www.ieee.org/conferences/publishing/templates.html)'],
            ['title' => 'No ISBN and page number', 'description' => 'Please make sure no ISBN code and page number written in the bottom part of each page.'],
            ['title' => 'Title', 'description' => 'Titles are generally capitalized except for minor words. Do not use math or special symbols. Avoid abbreviations.'],
            ['title' => 'Authors', 'description' => 'If more than 3 authors or text too wide, use alternate long format. Remove author numbering (1st, 2nd, …).'],
            ['title' => 'Abstract', 'description' => 'Max 250 words, preferable at least 200 words. No citations and special symbols.'],
            ['title' => 'Keywords', 'description' => 'lower caps, separated by comma, do not end with period "."'],
            ['title' => 'Section Heading', 'description' => 'small caps, except for minor words.'],
            ['title' => 'Subsection Heading', 'description' => 'Capitalize as title.'],
            ['title' => 'Abbreviations and Acronyms', 'description' => 'Define first time they appear in the abstract AND the body.'],
            ['title' => 'Equation', 'description' => 'Typed using Times New Roman/Symbol font. Avoid image. Numbered as in (1), flush right, equation centered.'],
            ['title' => 'Reference to equation', 'description' => 'Use "(1)", not "Eq. (1)" or "equation (1)", except at sentence start: "Equation (1) is..."'],
            ['title' => 'Figures', 'description' => 'Position at top or bottom of columns. Refer to as "Fig. 1". Caption ends with period ".". Vectorized or high-res.'],
            ['title' => 'Tables', 'description' => 'Position at top/bottom. Do not end caption with period. Headers centered, text left, numbers right aligned.'],
            ['title' => 'Citations', 'description' => 'Follow IEEE citation format.'],
            ['title' => 'References', 'description' => 'Check spacing between reference numbers and text per IEEE guidance.'],
            ['title' => 'Data or result', 'description' => 'Use English number format ex. 4.00 (not 4,00).'],
        ];
        foreach ($ieeeItems as $index => $item) {
            $editorial->items()->create([
                'title' => $item['title'],
                'description' => $item['description'],
                'is_required' => true,
                'sort_order' => $index + 1,
            ]);
        }

        foreach ($this->defaultEmailTemplates() as $template) {
            EmailTemplate::create([...$template, 'conference_id' => $conference->id]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function defaultFormSchema(): array
    {
        return [
            ['key' => 'co_authors', 'label' => 'Co-authors', 'type' => 'textarea', 'required' => false, 'help' => 'Additional co-authors data'],
            ['key' => 'notes', 'label' => 'Notes for Editorial Team', 'type' => 'textarea', 'required' => false, 'help' => 'Optional notes or instructions for the editorial team'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function defaultEmailTemplates(): array
    {
        return [
            [
                'key' => 'submission_received',
                'subject' => '[{{conference}}] Submission {{paper_code}} Received',
                'body' => "Dear {{author_name}},\n\nThank you for your submission to {{conference}}. We have successfully received your paper and recorded your submission details:\n\n<div style=\"margin: 20px 0; padding: 18px 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;\"><div style=\"font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 12px;\">Submission Details</div><table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"width: 100%; font-size: 13.5px; color: #334155; line-height: 1.6;\"><tr><td style=\"padding: 6px 0; font-weight: 700; color: #0f172a; width: 120px; vertical-align: top;\">Paper ID</td><td style=\"padding: 6px 0; color: #f47c20; font-weight: 800; font-size: 14px; vertical-align: top;\">{{paper_code}}</td></tr><tr><td style=\"padding: 6px 0; font-weight: 700; color: #0f172a; vertical-align: top;\">Paper Title</td><td style=\"padding: 6px 0; font-weight: 600; color: #1e293b; vertical-align: top;\">{{paper_title}}</td></tr><tr><td style=\"padding: 6px 0; font-weight: 700; color: #0f172a; vertical-align: top;\">Author Phone</td><td style=\"padding: 6px 0; color: #334155; vertical-align: top;\">{{author_phone}}</td></tr></table></div>\n\nYou can track the progress of your paper and manage your submission via your private author portal:\n\n<div style=\"margin: 20px 0; text-align: center;\"><a href=\"{{portal_url}}\" style=\"display: inline-block; background: #f47c20; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 800; padding: 12px 26px; border-radius: 8px; box-shadow: 0 3px 10px rgba(244,124,32,0.25);\">Track Submission</a></div>\n\nBest regards,\nEditorial Team\n{{conference}}",
                'is_enabled' => true,
            ],
            [
                'key' => 'revision_requested',
                'subject' => '[{{conference}}] Revision Required for Submission {{paper_code}}',
                'body' => "Dear Authors,\n\nThank you for your submission {{paper_code}} titled \"{{paper_title}}\" to {{conference}}.\n\nOur team has reviewed your submission. Revision is required for your manuscript before proceeding to editorial review:\n\n{{feedback}}\n\n📌 <strong>IMPORTANT INSTRUCTIONS FOR REVISION:</strong>\n<ul style=\"margin: 12px 0 20px 0; padding-left: 24px; color: #334155; line-height: 1.65;\"><li style=\"margin-bottom: 10px; padding-left: 6px;\">Please download and use the <strong>LATEST MANUSCRIPT FILE</strong> available on your private Author Portal as the base for your revisions, as the editorial team may have already performed initial formatting corrections on it.</li><li style=\"margin-bottom: 10px; padding-left: 6px;\"><strong>ONLY REVISE THE SPECIFIC SECTIONS REQUESTED FOR CORRECTION.</strong> Please leave all other already compliant sections untouched.</li><li style=\"margin-bottom: 10px; padding-left: 6px;\">For full checklist details and to upload your revised file, please access your private Author Portal using the button below:</li></ul>\n\n<div style=\"margin: 20px 0; text-align: center;\"><a href=\"{{portal_url}}\" style=\"display: inline-block; background: #f47c20; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 800; padding: 12px 26px; border-radius: 8px; box-shadow: 0 3px 10px rgba(244,124,32,0.25);\">Open Portal &amp; Upload Revision</a></div>\n\n<div style=\"margin-top: 16px; padding: 14px 18px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155;\"><strong style=\"color: #0f172a;\">Revision Deadline:</strong> {{deadline}}</div>\n\nBest regards,\n{{editor_name}}\n{{editor_job_title}}\n{{editor_affiliation}}",
                'is_enabled' => true,
            ],
            [
                'key' => 'paper_completed',
                'subject' => '[{{conference}}] {{paper_code}} Processing Completed',
                'body' => "Dear {{author_name}},\n\nWe are pleased to inform you that the editorial and review process for your paper {{paper_code}} has been completed successfully.\n\nThank you for contributing to {{conference}}.\n\nBest regards,\nEditorial Team\n{{conference}}",
                'is_enabled' => true,
            ],
            [
                'key' => 'deadline_reminder',
                'subject' => '[{{conference}}] Reminder: Pending Action for {{paper_code}}',
                'body' => "Dear {{author_name}},\n\nThis is a friendly reminder that an action or revision is pending for your manuscript {{paper_code}} submitted to {{conference}}.\n\nPlease visit your author portal to review the requirements:\n{{portal_url}}\n\nBest regards,\nEditorial Team\n{{conference}}",
                'is_enabled' => true,
            ],
        ];
    }
}
