<?php

namespace App\Http\Controllers;

use App\Enums\ReviewStage;
use App\Models\Conference;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChecklistController extends Controller
{
    public function edit(Conference $conference): View
    {
        $this->authorize('update', $conference);
        $templates = $conference->checklistTemplates()->with('items')->get()->keyBy(fn ($template) => $template->stage->value);

        return view('conferences.checklists', compact('conference', 'templates'));
    }

    public function update(Request $request, Conference $conference, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $conference);
        $validated = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*.name' => ['required', 'string', 'max:150'],
            'templates.*.items' => ['required', 'array', 'min:1', 'max:50'],
            'templates.*.items.*.title' => ['required', 'string', 'max:200'],
            'templates.*.items.*.description' => ['nullable', 'string', 'max:1000'],
            'templates.*.items.*.is_required' => ['nullable', 'boolean'],
            'templates.*.items.*.condition_type' => ['nullable', 'string', 'max:50'],
            'templates.*.items.*.condition_value' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($conference, $validated) {
            foreach ([ReviewStage::Editorial, ReviewStage::Reviewer] as $stage) {
                $payload = $validated['templates'][$stage->value] ?? null;
                if (! $payload) {
                    continue;
                }
                $template = $conference->checklistTemplates()->updateOrCreate(
                    ['stage' => $stage->value, 'is_active' => true],
                    ['name' => $payload['name']],
                );
                $template->items()->delete();
                foreach ($payload['items'] as $index => $item) {
                    $template->items()->create([
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                        'is_required' => (bool) ($item['is_required'] ?? false),
                        'condition_type' => $item['condition_type'] ?? null,
                        'condition_value' => $item['condition_value'] ?? null,
                        'sort_order' => $index + 1,
                    ]);
                }
            }
        });
        $audit->record('conference.checklists_updated', $conference, $conference);

        return back()->with('success', 'Checklist editorial dan reviewer diperbarui.');
    }
}
