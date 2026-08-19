<?php

use App\Enums\ReviewStage;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
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

        $templates = ChecklistTemplate::where('stage', ReviewStage::Editorial->value)->get();

        foreach ($templates as $template) {
            $template->items()->delete();

            foreach ($ieeeItems as $index => $item) {
                $template->items()->create([
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'is_required' => true,
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }

    public function down(): void {}
};
