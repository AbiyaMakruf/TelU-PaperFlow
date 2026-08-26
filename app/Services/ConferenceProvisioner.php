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
            [
                'title' => 'Template & Page Layout',
                'description' => 'Must use IEEE MS Word A4 template or LaTeX A4 template (make sure paper size is strictly set to A4; US Letter format is NOT allowed). Download templates from https://www.ieee.org/conferences/publishing/templates.html. Page layout: Top 1.9cm, Bottom 2.54cm, Left/Right 1.6cm with 2-column layout (width 8.58cm, spacing 0.63cm) and 10pt Times New Roman body text.',
            ],
            [
                'title' => 'No ISBN & Page Number',
                'description' => 'Ensure no page numbers or copyright/ISBN notices are written in the header or footer of any page (publisher will add these upon acceptance).',
            ],
            [
                'title' => 'Paper Title Format',
                'description' => 'Use Title Case (capitalize first letter of each word except minor words: a, an, and, as, at, but, by, for, in, nor, of, on, or, the, to, up). Do not use math, special symbols, or abbreviations.',
            ],
            [
                'title' => 'Author Information & Affiliations',
                'description' => 'Remove labels/numbering (1st, 2nd, ...). Max 3 authors per row: for 4 authors, place Authors 1–3 on row 1 and Author 4 centered under Author 2 on row 2; for 5 authors, place Authors 1–3 on row 1, Author 4 under Author 1, and Author 5 under Author 2. Author block sequence: 1) Author Name in normal font (must match EDAS name exactly), 2) Department & Affiliation in italic, 3) City & Country in normal font, 4) Email in normal font. No postal code needed.',
            ],
            [
                'title' => 'Abstract Formatting',
                'description' => '200–250 words in 9pt Times New Roman. Bold heading, italic "Abstract" label, normal main text. Must not contain citations, equations, or special symbols.',
            ],
            [
                'title' => 'Keywords / Index Terms',
                'description' => 'Use "Keywords" (DOCX) or "Index Terms" (LaTeX). Written in lowercase, separated by commas, and must not end with a period "."',
            ],
            [
                'title' => 'Section Headings (Heading 1)',
                'description' => 'Formatted in Small Caps, except for minor words (a, an, and, as, at, but, by, for, in, nor, of, on, or, the, to, up) unless starting or ending the section heading.',
            ],
            [
                'title' => 'Subsection Headings (Heading 2 & 3)',
                'description' => 'Capitalize in Title Case format (capitalize the first letter of each major word).',
            ],
            [
                'title' => 'Abbreviations & Acronyms',
                'description' => 'Define acronyms upon first mention in the abstract AND again upon first mention in the main body text. Very common abbreviations need no definition.',
            ],
            [
                'title' => 'Equation Formatting',
                'description' => 'Typed using Times New Roman or Symbol font (never images). Equations must be centered, with equation numbers in parentheses (1) aligned flush right.',
            ],
            [
                'title' => 'In-Text Equation Citation',
                'description' => 'Refer to equations as "(1)", not "Eq. (1)" or "equation (1)", except at the beginning of a sentence where "Equation (1) is..." should be used.',
            ],
            [
                'title' => 'Figure Format & Captions',
                'description' => 'Place at top/bottom of columns. High-res/vectorized (unblurred, readable text ≤11pt). Refer as "Fig. 1". Caption placed BELOW in 8pt sentence case, ending with a period "."',
            ],
            [
                'title' => 'Table Format & Captions',
                'description' => 'Place at top/bottom of columns. Caption placed ABOVE in Roman numeral/Camel Case without ending period. Headers centered, text left-aligned, numbers right-aligned (2 decimal precision e.g. 3.14). Font 8pt.',
            ],
            [
                'title' => 'In-Text Citations & Order',
                'description' => 'Must follow IEEE sequential numerical ordering starting from [1], [2], and so on in order of appearance. Use reference management tools like Mendeley to easily format citations. Do not use author-year styles.',
            ],
            [
                'title' => 'References List & Language',
                'description' => 'Heading "REFERENCES". "et al." is ONLY allowed if the paper has MORE THAN 6 authors (otherwise list all author names). Do NOT cite papers written in Indonesian; all references MUST be in English. Capitalize only the first word of paper titles (except proper nouns). Balance columns on last page.',
            ],
            [
                'title' => 'Numerical & Decimal Formatting',
                'description' => 'Use standard English decimal format with dots (e.g. 4.00 or 3.14) instead of commas (4,00).',
            ],
            [
                'title' => 'Page Count Limit & Extra Fee',
                'description' => 'Maximum manuscript length is 6 pages. Submissions exceeding 6 pages must pay an extra page fee according to conference policy.',
            ],
            [
                'title' => 'Others / Custom Editor Notes',
                'description' => 'For editorial staff to specify custom formatting notes or compliance observations not covered by the standard template items.',
            ],
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
                'body' => "Dear {{author_name}},\n\nWe are pleased to confirm that your manuscript has met the editorial and technical requirements. Our editorial team has completed the IEEE PDF eXpress process and uploaded the final manuscript to EDAS on your behalf.\n\n<div style=\"margin:20px 0;padding:18px 20px;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;\"><div style=\"font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#64748b;margin-bottom:12px;\">Final Manuscript Details</div><table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;font-size:13.5px;color:#334155;line-height:1.6;\"><tr><td style=\"padding:6px 0;font-weight:700;color:#0f172a;width:120px;vertical-align:top;\">Paper ID</td><td style=\"padding:6px 0;color:#f47c20;font-weight:800;font-size:14px;vertical-align:top;\">{{paper_code}}</td></tr><tr><td style=\"padding:6px 0;font-weight:700;color:#0f172a;vertical-align:top;\">Paper Title</td><td style=\"padding:6px 0;color:#1e293b;font-weight:600;vertical-align:top;\">{{paper_title}}</td></tr></table></div>\n\nPlease review the final manuscript in EDAS to confirm that it is correct. If you find any discrepancy, contact your assigned PIC using the contact details available in your Author Portal.\n\n<div style=\"margin:20px 0;text-align:center;\"><a href=\"{{portal_url}}\" style=\"display:inline-block;background:#f47c20;color:#ffffff;text-decoration:none;font-size:14px;font-weight:800;padding:12px 26px;border-radius:8px;box-shadow:0 3px 10px rgba(244,124,32,0.25);\">Open Author Portal</a></div>\n\nBest regards,\nEditorial Team\n{{conference}}",
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
