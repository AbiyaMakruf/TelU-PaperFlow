<?php

use App\Enums\ReviewStage;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
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

        $templates = ChecklistTemplate::where('stage', ReviewStage::Editorial->value)->get();

        foreach ($templates as $template) {
            // Remove old default items if present
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

    public function down(): void
    {
        // Revert is optional
    }
};
