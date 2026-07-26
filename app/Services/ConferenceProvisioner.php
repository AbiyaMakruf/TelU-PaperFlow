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
                'body' => "Dear {{author_name}},\n\nThank you for your submission {{paper_code}} to {{conference}}. We have successfully received your paper.\n\nYou can track the progress of your paper and manage your submission via your private author portal:\n{{portal_url}}\n\nBest regards,\nEditorial Team\n{{conference}}",
                'is_enabled' => true,
            ],
            [
                'key' => 'revision_requested',
                'subject' => '[{{conference}}] Revision Required for Submission {{paper_code}}',
                'body' => "Dear Authors,\n\nThank you for your submission {{paper_code}} to {{conference}}.\n\nOur team has reviewed your submission. Revision is required for your manuscript before proceeding to peer review:\n\n{{feedback}}\n\n📌 IMPORTANT INSTRUCTIONS FOR REVISION:\n• Please download and use the LATEST MANUSCRIPT FILE available on your private Author Portal as the base for your revisions, as the editorial team may have already performed initial formatting corrections on it.\n• ONLY REVISE THE SPECIFIC SECTIONS REQUESTED FOR CORRECTION. Please leave all other already compliant sections untouched.\n• For full checklist details and to upload your revised file, please access your private Author Portal:\n{{portal_url}}\n\n<strong>Revision Deadline: {{deadline}}</strong>\n\nBest regards,\n{{editor_name}}\n{{editor_job_title}}\n{{editor_affiliation}}",
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
