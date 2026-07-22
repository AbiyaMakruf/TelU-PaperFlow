# Paperflow Agent Context

This file is the primary handoff document for any AI/coding agent working on this repository. Read it before changing code. The application language and UI copy are Indonesian; code identifiers remain English.

## Product summary

Paperflow is a Laravel 12 editorial workflow application for academic conferences. It replaces separate Google Forms and spreadsheets with conference-specific public submission forms, staff assignments, editorial/reviewer checklists, file versioning, author communication, progress tracking, EDAS handoff, reporting, and operational monitoring.

Production data services:

- PostgreSQL database hosted by Supabase, accessed server-side through the Session Pooler.
- Each conference chooses its file provider: private Supabase Storage (default) or an authorized Google Drive folder.
- Gmail SMTP sends queued, branded HTML email.
- Browser clients never receive the Supabase secret key or Google OAuth token.

## Technology and compatibility

- Laravel 12
- PHP `^8.2`; `ext-zip` is required for DOCX preview
- Blade, Alpine.js with the Collapse plugin, Tailwind CSS 4, Vite
- PostgreSQL 17 in Supabase; SQLite is used by automated tests
- Database queue driver
- The Composer platform is pinned to PHP 8.2 for dependency resolution

Do not upgrade Laravel, Symfony, PHP requirements, or JavaScript dependencies casually. Run the complete verification sequence before committing dependency changes.

## Authentication and roles

Staff use one login page and can sign in with username or email.

- Superadmin: creates users and conferences, manages all data, sees application monitoring, impersonates users.
- Conference Admin: configures a conference, form, team, checklist, email, storage, branding, assignment, and workflow administration.
- Editorial: works only on assigned papers and editorial checklist/workflow.
- Reviewer: works only on assigned papers, reviewer/approval workflow, IEEE PDF eXpress status, and EDAS error notes.
- Viewer: read-only access to conference progress.

Superadmin creates a user using name and username only. New accounts receive password `user1234`. First login forces the user to provide an email and choose a new password. Password validation is intentionally only a minimum of 8 characters; do not add complexity rules unless explicitly requested.

Authorization is implemented with policies and conference membership checks. Never replace these checks with request parameters, UI hiding, or client-side checks.

## Primary URLs

- `/login`: staff login
- `/dashboard`: role-scoped dashboard (includes GCP-style workspace switcher, PIC Workload Matrix & format stats)
- `/papers`: role-scoped paper list with bulk actions & duplicate detection flags
- `/workspace/switch`: active conference workspace selector handler
- `/{conference-slug}`: public conference landing page (search modal enabled, hidden default list)
- `/{conference-slug}/submit`: public author submission form
- `/submission/access/{token}`: private tokenized author portal (live checklist monitoring, detail updates, optional PDF Petunjuk Revisi upload)
- `/conferences/{conference}`: conference administration
- `/audit-logs`: scoped audit log
- `/editor-performance`: editor performance report
- `/notifications`: current staff notifications
- `/admin/monitoring`: superadmin failed-job and error monitoring
- `/admin/users/{user}/impersonate`: superadmin user impersonation

Keep fixed routes above the final `/{conference:slug}` catch-all route in `routes/web.php`.

## Submission workflow & Features

Statuses are defined in `App\Enums\SubmissionStatus`. The allowed transition graph is centralized in `App\Services\SubmissionWorkflow`.

Typical workflow:

1. Author enters the conference Paper ID, required phone number, corresponding-author data, optional structured co-authors, and uploads an editable DOCX or a ZIP containing LaTeX sources.
2. Conference Admin validates or returns it for correction.
3. Admin assigns Editorial and Reviewer PICs, confirms whether the editable manuscript is DOCX or LaTeX, and optionally sets a deadline. Mandatory reassignment reasons are required when replacing existing PICs.
4. Editorial completes 16 required IEEE compliance checklist items (with accordion guidance) and may request author revision. Editor can auto-generate revision feedback from unchecked items.
5. Author receives notification and opens tokenized portal (`/submission/access/{token}`) where live checklist monitoring appears after revision request. Author can edit submission details and upload editable revision + optional PDF Petunjuk Revisi.
6. Editorial sends the paper to Reviewer.
7. Reviewer inspects editor's work, sets IEEE PDF eXpress status (`Pending`, `Passed`, `Failed`), logs EDAS error notes (with quick-error preset buttons), requests changes, or marks ready for EDAS.
8. Editorial records EDAS upload reference and notes.
9. Reviewer approves the EDAS result; only then is the paper marked Done.

Reject and Withdraw are explicit terminal actions. Every status transition writes status history, audit data, and in-app notifications for relevant assignees.

Do not update `submissions.status` directly when a transition should be validated. Use `SubmissionWorkflow`.

## Key Feature Capabilities

- **GCP-Style Workspace Selector**: Header & drawer active conference selector scoping `VisibleSubmissions` to `session('active_conference_id')`.
- **Duplicate Submission Detection**: Automatic flags for title similarity, corresponding author email match, or exact file checksums.
- **Bulk Actions**: Bulk assignment (PIC, format, deadline) & bulk status transitions (validate/accept, reject, withdraw).
- **IEEE Editorial Checklist**: 16 default IEEE formatting rules with guidance accordions and auto-generated revision feedback templates.
- **Interactive CC Email Chips**: Alpine.js tag input for CC emails with remove buttons.
- **Author Live Checklist Monitoring**: Live checklist results card on author portal visible after editor requests revision / sends feedback.
- **Reviewer EDAS & PDF eXpress Management**: Reviewer-restricted controls for IEEE PDF eXpress status and EDAS error logging.
- **PIC Workload Summary Matrix**: Dashboard table matching format checking spreadsheet structure (Total, Belum, In Progress, Menunggu Jawaban, Revised by Editor, Revised by Author, Selesai).

## File storage design

`conferences.storage_provider` controls where new files for that conference are stored:

- `supabase`: private Supabase bucket through `PrivateFileStorage`
- `google_drive`: conference-specific OAuth folder through `GoogleDriveStorage`

`ConferenceFileStorage` routes upload/download/preview operations to the correct provider. Each `file_versions` row records its own `disk`, path/external ID, external metadata, and `file_category` (`editable_manuscript`, `revision_guidance_pdf`). Switching provider does not move or delete previous versions. A single paper may therefore have versions on both providers.

Important behavior:

- Google Drive download is proxied through Laravel and returned as an attachment; do not redirect to `webViewLink`.
- Google folder discovery requires exactly one folder with the configured name and validates `canAddChildren`.
- Shared Drive flags are included in Drive API requests.
- Supabase files use short-lived signed URLs.
- Failed uploads for existing submissions are copied to private local temporary storage and represented by `upload_attempts`; authorized users can retry without selecting the file again.
- PDF preview is inline. DOCX preview extracts readable text using `ZipArchive`. Other types remain download-only.
- Conference settings control allowed extensions and maximum size (1-100 MB).

Never make the Supabase bucket public. Never expose `SUPABASE_SECRET_KEY`, database credentials, Google refresh/access tokens, or Gmail App Password in code, logs, Vite variables, tests, or committed documentation.

## Public submission protection

The public form uses:

- Cloudflare Turnstile checkbox CAPTCHA with mandatory server-side Siteverify validation when configured
- Named rate limiter `public-submission`: 5/minute and 40/day per IP
- File MIME/extension and size validation from conference settings

Other named limiters protect author revision, staff email actions, and file downloads. Turnstile uses `TURNSTILE_ENABLED`, `TURNSTILE_SITE_KEY`, and `TURNSTILE_SECRET_KEY`; when credentials are absent it is disabled so local development remains usable.

## Email design and queue

Conference email is created by `ConferenceMailer`, logged in `email_logs`, queued as `SendLoggedEmail`, and rendered by `PaperflowMail` using:

- `resources/views/emails/paperflow.blade.php`
- `resources/views/emails/paperflow-text.blade.php`

Email is HTML-first with a plain-text fallback. It uses the conference sender name, primary/accent colors, and optional logo. Password-reset and SMTP diagnostic messages use the same Paperflow design.

Conference Admin can preview edited templates live and queue a test-send before saving. Conference-level and template-level default CC recipients appear in the editorial composer but remain removable. Email logs persist the rendered body and sender user: Conference Admin sees conference email, superadmin sees all email, and Editorial sees only email they sent. Failed email with a stored body can be re-sent from monitoring or the paper history.

Audit logs are restricted to superadmin and active Conference Admin memberships. Staff profiles store editable name, email, WhatsApp country code/number, committee role, and affiliation. These fields populate email signatures and WhatsApp contact links. The paper page provides an Editorial WhatsApp action whose message is prefilled from outstanding checklist items. Author and staff phone numbers are normalized from an international country-code selector.

The sender address remains the global Gmail address; Conference Admin changes only the display name. Queue workers must be restarted after mail code/config changes:

```bash
php artisan optimize:clear
php artisan queue:restart
php artisan queue:work --tries=3
```

## Conference branding

Branding and upload rules live in the `conferences.settings` JSON object:

- `allowed_extensions`
- `max_file_mb`
- `brand_primary`
- `brand_accent`
- `brand_tagline`
- `brand_logo`

Brand assets are stored on Laravel's public disk under `conference-branding`; `php artisan storage:link` is required. Branding is applied to conference landing, submission form, and email.

When updating `settings`, merge with existing values. Do not overwrite unrelated settings.

## Database and Supabase

Laravel migrations in `database/migrations` are the source of truth. Latest applied production migrations through:
- `2026_07_21_001200_add_duplicate_check_and_bulk_reassignment_fields.php`
- `2026_07_21_001300_update_editorial_checklist_items_to_ieee.php`
- `2026_07_21_001400_add_pdf_express_and_revision_guidance_fields.php`
- `2026_07_22_000100_add_staff_profiles_and_email_ownership.php`

Application tables are server-only. RLS is enabled without anon/authenticated policies because the browser does not use Supabase Data API for these tables. Laravel connects with the server database role.

The configured PostgreSQL connection uses the Supabase Session Pooler, not the direct database hostname.

## Important models and services

- `Conference`: conference lifecycle, settings, storage and branding helpers
- `Submission`: workflow aggregate, PDF eXpress status, EDAS error notes, revision substatus, and related files/authors/history
- `SubmissionWorkflow`: guarded transitions, reassignment reasons, and assignment side effects
- `VisibleSubmissions`: role-scoped and active-workspace-scoped paper query
- `ConferenceProvisioner`: default forms, 16 IEEE checklist templates, email templates and duplication
- `ConferenceFileStorage`: storage-provider router
- `PrivateFileStorage`: Supabase/private-local implementation
- `GoogleDriveStorage`: OAuth, folder validation, upload/download
- `ConferenceMailer`: template rendering, log creation, job dispatch
- `AuditLogger`: audit persistence with `newValues` parameter

Prefer extending these services over duplicating workflow, storage, visibility, or email logic inside controllers.

## Verification before handoff

Run all of the following after meaningful changes:

```bash
vendor/bin/pint --dirty
php artisan test
php artisan view:cache
pnpm build
php artisan migrate:status
```

For schema changes, also run:

```bash
php artisan migrate --force
```

Current baseline:

- **52 tests**
- **252 assertions**
- Production Vite build passes
- Supabase migration `2026_07_22_000100_add_staff_profiles_and_email_ownership` is batch 11 / Ran

## Git workflow

The active working branch is `agent/build-paperflow`; remote is `origin` on the `TelU-PaperFlow` GitHub repository. Every completed change must be committed and pushed.

Before committing:

```bash
git diff --check
git status --short
```

Preserve user changes and never commit `.env`, credentials, runtime logs, temporary uploads, generated preview files, or OAuth tokens.

## Change discipline

- Keep Indonesian UI copy consistent and understandable to non-technical editorial staff.
- Preserve the navy/orange/warm visual system unless conference branding overrides public surfaces.
- Keep navigation, tables, forms, and workflow actions usable at 320px mobile width; important data tables should have a card alternative rather than relying only on horizontal scrolling.
- Keep authorization server-side and scope all operational views to visible conferences.
- Record meaningful administrative and workflow mutations in the audit log.
- Send assignment/status notifications through the database notification channel.
- Use queued, logged email rather than direct ad-hoc SMTP calls.
- Update this file when architecture, environment requirements, workflow, or baseline verification changes.
