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
- Blade, Alpine.js, Tailwind CSS 4, Vite
- PostgreSQL 17 in Supabase; SQLite is used by automated tests
- Database queue driver
- The Composer platform is pinned to PHP 8.2 for dependency resolution

Do not upgrade Laravel, Symfony, PHP requirements, or JavaScript dependencies casually. Run the complete verification sequence before committing dependency changes.

## Authentication and roles

Staff use one login page and can sign in with username or email.

- Superadmin: creates users and conferences, manages all data, sees application monitoring.
- Conference Admin: configures a conference, form, team, checklist, email, storage, branding, assignment, and workflow administration.
- Editorial: works only on assigned papers and editorial checklist/workflow.
- Reviewer: works only on assigned papers and reviewer/approval workflow.
- Viewer: read-only access to conference progress.

Superadmin creates a user using name and username only. New accounts receive password `user1234`. First login forces the user to provide an email and choose a new password. Password validation is intentionally only a minimum of 8 characters; do not add complexity rules unless explicitly requested.

Authorization is implemented with policies and conference membership checks. Never replace these checks with request parameters, UI hiding, or client-side checks.

## Primary URLs

- `/login`: staff login
- `/dashboard`: role-scoped dashboard
- `/papers`: role-scoped paper list
- `/{conference-slug}`: public conference landing page
- `/{conference-slug}/submit`: public author submission form
- `/submission/access/{token}`: private tokenized author portal
- `/conferences/{conference}`: conference administration
- `/audit-logs`: scoped audit log
- `/editor-performance`: editor performance report
- `/notifications`: current staff notifications
- `/admin/monitoring`: superadmin failed-job and error monitoring

Keep fixed routes above the final `/{conference:slug}` catch-all route in `routes/web.php`.

## Submission workflow

Statuses are defined in `App\Enums\SubmissionStatus`. The allowed transition graph is centralized in `App\Services\SubmissionWorkflow`.

Typical workflow:

1. Author enters the conference Paper ID, required phone number, corresponding-author data, optional structured co-authors, and uploads an editable DOCX or a ZIP containing LaTeX sources.
2. Conference Admin validates or returns it for correction.
3. Admin assigns Editorial and Reviewer PICs, confirms whether the editable manuscript is DOCX or LaTeX, and optionally sets a deadline.
4. Editorial completes required checklist items and may request author revision.
5. Editorial sends the paper to Reviewer.
6. Reviewer requests changes or marks it ready for EDAS.
7. Editorial records EDAS upload reference and notes.
8. Reviewer approves the EDAS result; only then is the paper marked Done.

Reject and Withdraw are explicit terminal actions. Every status transition writes status history, audit data, and in-app notifications for relevant assignees.

Do not update `submissions.status` directly when a transition should be validated. Use `SubmissionWorkflow`.

## File storage design

`conferences.storage_provider` controls where new files for that conference are stored:

- `supabase`: private Supabase bucket through `PrivateFileStorage`
- `google_drive`: conference-specific OAuth folder through `GoogleDriveStorage`

`ConferenceFileStorage` routes upload/download/preview operations to the correct provider. Each `file_versions` row records its own `disk`, path/external ID, and external metadata. Switching provider does not move or delete previous versions. A single paper may therefore have versions on both providers.

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

- A built-in arithmetic CAPTCHA stored in the session
- Named rate limiter `public-submission`: 5/minute and 40/day per IP
- File MIME/extension and size validation from conference settings

Other named limiters protect author revision, staff email actions, and file downloads. CAPTCHA can be controlled with `PAPERFLOW_CAPTCHA_ENABLED`; tests disable it explicitly.

## Email design and queue

Conference email is created by `ConferenceMailer`, logged in `email_logs`, queued as `SendLoggedEmail`, and rendered by `PaperflowMail` using:

- `resources/views/emails/paperflow.blade.php`
- `resources/views/emails/paperflow-text.blade.php`

Email is HTML-first with a plain-text fallback. It uses the conference sender name, primary/accent colors, and optional logo. Password-reset and SMTP diagnostic messages use the same Paperflow design.

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

## Operational features

- Audit log is scoped to conferences visible to the current staff member.
- Email history appears on the paper detail page.
- Database notifications cover assignment and status changes.
- Every active conference member can see the conference paper table and open paper details; mutation permissions remain role/assignment scoped.
- Paper filters include conference, status, editor, reviewer, overdue, search, and date range, with whitelisted server-side sorting.
- Editor performance shows paper count, average processing days, and overdue count.
- Full CSV report includes contact, workflow, deadline, file-version, and EDAS fields.
- Superadmin monitoring shows `failed_jobs`, supports retry, and shows recent Laravel ERROR log lines.

## Database and Supabase

Laravel migrations in `database/migrations` are the source of truth. Current production migrations through `2026_07_21_001100_add_author_paper_id_and_manuscript_format.php` have been applied to Supabase.

Application tables are server-only. RLS is enabled without anon/authenticated policies because the browser does not use Supabase Data API for these tables. Laravel connects with the server database role.

For new tables in the exposed `public` schema:

1. Add a Laravel migration.
2. Enable RLS explicitly in PostgreSQL.
3. Do not grant anon/authenticated access unless a reviewed browser-side use case genuinely requires it.
4. Test locally with SQLite and run the migration against Supabase before claiming completion.

The configured PostgreSQL connection uses the Supabase Session Pooler, not the direct database hostname.

## Important models and services

- `Conference`: conference lifecycle, settings, storage and branding helpers
- `Submission`: workflow aggregate and related files/authors/history
- `SubmissionWorkflow`: guarded transitions and assignment side effects
- `VisibleSubmissions`: role-scoped paper query
- `ConferenceProvisioner`: default forms/checklists/email templates and duplication
- `ConferenceFileStorage`: storage-provider router
- `PrivateFileStorage`: Supabase/private-local implementation
- `GoogleDriveStorage`: OAuth, folder validation, upload/download
- `ConferenceMailer`: template rendering, log creation, job dispatch
- `AuditLogger`: audit persistence

Prefer extending these services over duplicating workflow, storage, visibility, or email logic inside controllers.

## Local environment

Never commit `.env`. `.env.example` contains safe placeholders only.

Relevant variables include:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=aws-0-REGION.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.PROJECT_REF
DB_PASSWORD=
DB_SSLMODE=require

PAPERFLOW_STORAGE_DRIVER=supabase
SUPABASE_URL=
SUPABASE_SECRET_KEY=
SUPABASE_STORAGE_BUCKET=paperflow-private

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/google-drive/callback
GOOGLE_DRIVE_FOLDER_NAME="{conference}"

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=Paperflow

QUEUE_CONNECTION=database
PAPERFLOW_CAPTCHA_ENABLED=true
```

If Gmail SMTP ports 587 and 465 are blocked by the machine/network, changing Laravel code or the App Password will not fix connectivity. Diagnose TCP access first.

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

Current baseline at the time of this handoff:

- 46 tests
- 219 assertions
- Production Vite build passes
- Supabase migration `001100` is batch 7 / Ran

## Git workflow

The active working branch is `agent/build-paperflow`; remote is `origin` on the `TelU-PaperFlow` GitHub repository. The user expects every completed change to be committed and pushed.

Before committing:

```bash
git diff --check
git status --short
```

Preserve user changes and never commit `.env`, credentials, runtime logs, temporary uploads, generated preview files, or OAuth tokens.

## Known operational requirements

- A persistent queue worker is required in production.
- Public logo URLs require correct `APP_URL`, HTTPS, and `public/storage` link.
- Google OAuth account must have Editor/Content Manager permission on the target folder.
- Folder names must currently be unique across folders visible to the OAuth account.
- Google Drive is selected per conference; database records always remain in Supabase/PostgreSQL.
- Existing files are not migrated when a conference switches storage provider.

## Change discipline

- Keep Indonesian UI copy consistent and understandable to non-technical editorial staff.
- Preserve the navy/orange/warm visual system unless conference branding overrides public surfaces.
- Keep authorization server-side and scope all operational views to visible conferences.
- Record meaningful administrative and workflow mutations in the audit log.
- Send assignment/status notifications through the database notification channel.
- Use queued, logged email rather than direct ad-hoc SMTP calls.
- Use `apply_patch` for edits and run tests proportional to risk.
- Update this file when architecture, environment requirements, workflow, or baseline verification changes.
