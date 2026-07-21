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
            'name' => 'Pemeriksaan Editorial',
            'stage' => ReviewStage::Editorial,
        ]);
        foreach (['Template conference sudah digunakan', 'Metadata dan daftar author sesuai', 'Format halaman dan referensi sesuai', 'File dapat dibuka atau dikompilasi'] as $index => $title) {
            $editorial->items()->create(['title' => $title, 'is_required' => true, 'sort_order' => $index + 1]);
        }

        $reviewer = $conference->checklistTemplates()->create([
            'name' => 'Final Review',
            'stage' => ReviewStage::Reviewer,
        ]);
        foreach (['Seluruh feedback editorial sudah diselesaikan', 'File final siap diunggah ke EDAS'] as $index => $title) {
            $reviewer->items()->create(['title' => $title, 'is_required' => true, 'sort_order' => $index + 1]);
        }

        foreach ($this->defaultEmailTemplates() as $template) {
            EmailTemplate::create([...$template, 'conference_id' => $conference->id]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function defaultFormSchema(): array
    {
        return [
            ['key' => 'affiliation', 'label' => 'Afiliasi', 'type' => 'text', 'required' => true, 'help' => 'Institusi corresponding author'],
            ['key' => 'country', 'label' => 'Negara', 'type' => 'text', 'required' => true, 'help' => null],
            ['key' => 'co_authors', 'label' => 'Co-authors', 'type' => 'textarea', 'required' => false, 'help' => 'Satu nama per baris'],
            ['key' => 'notes', 'label' => 'Catatan untuk tim editorial', 'type' => 'textarea', 'required' => false, 'help' => null],
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
                'subject' => '[{{conference}}] Revision Requested for {{paper_code}}',
                'body' => "Dear {{author_name}},\n\nThe editorial team has reviewed your manuscript {{paper_code}} and requested the following revisions:\n\n{{feedback}}\n\nPlease submit your updated revision via your author portal:\n{{portal_url}}\n\nBest regards,\nEditorial Team\n{{conference}}",
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
