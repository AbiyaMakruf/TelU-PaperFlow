# Paperflow - Executive LLM Handoff & Knowledge Base

> **Handoff Target**: Any new LLM / AI Coding Agent (e.g. Claude 3.7 Sonnet, GPT-4o, Gemini 1.5/2.0 Pro)  
> **Last Verified Baseline**: 127 Feature Tests (646 Assertions) Passing 100% | Laravel 12 | PHP 8.2 | Tailwind CSS 4 | Vite | PostgreSQL 17 / Supabase  
> **Active Git Branch**: `agent/build-paperflow` (synced and merged to `main`)  
> **Primary Repository Documentation**: [`AGENTS.md`](./AGENTS.md)

---

## 1. Product & Architecture Overview

**Paperflow** is a specialized, production-ready editorial workflow management application built with Laravel 12 for academic conferences (especially IEEE and Scopus indexed conferences). It replaces cumbersome Google Forms and spreadsheets with a unified system managing:
- Public submission forms with CAPTCHA (Cloudflare Turnstile).
- Staff role scoping and workspace switching (GCP-style).
- 16-point default IEEE compliance editorial checklist.
- Reviewer IEEE PDF eXpress verification and EDAS upload handoff.
- Tokenized private Author Portal for real-time tracking, revisions, and guidance downloads.
- Conference-scoped EDAS reconciliation with CSV/PDF reporting.
- Centralized system monitoring, audit logs, and branded queued HTML emails.

### Production Tech Stack:
- **Framework**: Laravel 12 (`^12.0`)
- **PHP**: Pinned to PHP 8.2 (`ext-zip`, `ext-pdo_sqlite`, `ext-pdo_pgsql`, `ext-mbstring`, `ext-fileinfo`)
- **Frontend**: Blade templates, Alpine.js with Collapse plugin, Tailwind CSS v4, Vite
- **Database**: PostgreSQL 17 (hosted on Supabase, connected server-side via Session Pooler); SQLite is used for local automated testing
- **Storage**: Conference-configurable: Private Supabase Storage (default) or Google Drive API (OAuth)
- **Queues & Mail**: Database queue driver (`jobs` table) with persistent worker (`php artisan queue:work --tries=3`) and Gmail SMTP via `ConferenceMailer`

---

## 2. Authentication & 5 Core User Roles

All staff log in via `/login` using username or email:
1. **Superadmin**: Full administrative authority, system purge/backup tools, application monitoring, and user impersonation (`/admin/users/{user}/impersonate`).
2. **Conference Admin**: Manages conference branding, settings, team memberships, form builder, email templates, and paper assignments.
3. **Editorial**: Works strictly on assigned papers; completes the 16 IEEE checklist items, requests author revisions, and advances compliant papers to reviewers.
4. **Reviewer**: Inspects editor's work, sets IEEE PDF eXpress status (`Pending`, `Passed`, `Failed`), records EDAS error notes, or approves ready for EDAS.
5. **Viewer**: Read-only access to conference papers and metrics.

> **Golden Rule**: Superadmin creates new users with default password `user1234`. Upon first login, users are forced to provide an email and choose a new password. Password validation is strictly a **minimum of 8 characters**—never add arbitrary complexity rules unless explicitly requested.

---

## 3. Submission Lifecycle & Workflow Engine

All submission transitions are governed strictly by `App\Services\SubmissionWorkflow` and `App\Enums\SubmissionStatus`. **Never update `$submission->status` directly in controllers without passing through `SubmissionWorkflow`**.

### Workflow Pipeline:
- **Submitted** -> Conference Admin validates paper -> moves to **Waiting for Editor Assignment**.
- **Waiting for Editor Assignment** -> Admin assigns Editorial PIC & Reviewer PIC with initial page count -> moves to **Editorial Review in Progress**.
- **Editorial Review in Progress** -> Editor completes IEEE checklist:
  - If issues found -> sends revision request to author -> moves to **Waiting for Author Revision**.
  - When author submits revision through portal -> returns to **Editorial Review in Progress**.
  - When compliant -> Editor inputs mandatory final page count and advances -> moves to **Pre-EDAS Technical Review**.
- **Pre-EDAS Technical Review** -> Reviewer checks PDF eXpress:
  - If changes needed -> returns to **Reviewer Revision Requested** (or back to Editorial).
  - When passed -> Reviewer uploads Camera-Ready PDF and approves -> moves to **Ready for EDAS Upload**.
- **Ready for EDAS Upload** -> Editorial uploads final camera-ready to EDAS and enters EDAS reference -> Reviewer verifies -> moves to terminal **Completed** state.
- Explicit terminal actions: **Withdrawn** and **Rejected**.

---

## 4. Key Directory & Codebase Map

| Component / Layer | Key File Path | Purpose |
| :--- | :--- | :--- |
| **Workflow Engine** | `app/Services/SubmissionWorkflow.php` | Transition graph, guard rules, status history & assignee notifications |
| **Status Enum** | `app/Enums/SubmissionStatus.php` | Status cases, badge colors, and Indonesian/English display labels |
| **Papers Controller** | `app/Http/Controllers/SubmissionController.php` | Paper index, show, edit details, assign PIC, status transitions |
| **Author Portal** | `app/Http/Controllers/AuthorPortalController.php` | Tokenized author view, detail editing, revision upload, file download |
| **Checklists** | `app/Http/Controllers/ChecklistController.php` | IEEE checklist template management and item evaluation |
| **EDAS Reconciliation**| `app/Http/Controllers/EdasReconciliationController.php` | CSV import, match algorithms, DB persistence, PDF/CSV export |
| **File Storage Router**| `app/Services/ConferenceFileStorage.php` | Routes upload/download/preview between Supabase & Google Drive |
| **Private Supabase** | `app/Services/PrivateFileStorage.php` | Supabase bucket storage, signed URLs with forced download attachment |
| **Google Drive** | `app/Services/GoogleDriveStorage.php` | OAuth token management, folder discovery, proxied attachment stream |
| **Email Service** | `app/Services/ConferenceMailer.php` | Branded HTML mail renderer, dynamic signatures, CC handling, logging |
| **Paper List Blade** | `resources/views/submissions/index.blade.php` | Quick presets, live debounced search, responsive table & bulk actions |
| **Paper Detail Blade** | `resources/views/submissions/show.blade.php` | Collapsible accordions, staff action bar, checklist evaluation, file versions |
| **Author Portal Blade**| `resources/views/public/portal.blade.php` | Author submission details, live checklist, revision upload, guidance PDF |
| **Web Routes** | `routes/web.php` | Web routes (public, staff, author, monitoring, admin) |

---

## 5. Recent Core Capabilities & Architectural Patterns

When building or modifying features, adhere to these established patterns:

1. **Instant Debounced Live Search (DOM Swap)**:
   - Implemented via `window.papersManager` Alpine.js component in `resources/views/submissions/index.blade.php`.
   - Utilizes `DOMParser()` to replace `#submissions-table-container`, `#submissions-pagination-container`, and `#submissions-total-count` without full page reload.
   - Search input uses a **Flex Input Group** structure (`flex items-center gap-3`) ensuring the search icon and placeholder text never collide.
2. **Aggregated Single-Query Preset Counts**:
   - Tab counts on the papers list (`All Papers`, `My Assigned Tasks`, `Waiting Author Revision`, `Ready for EDAS`) are calculated in `SubmissionController::index()` via a single `selectRaw("COUNT(*), COUNT(CASE WHEN ...)")` query to strictly avoid $N+1$ query regressions and satisfy `PerformanceBenchmarkTest`.
3. **Author Portal File Presentation & Forced Attachment Downloads**:
   - File cards display clear English descriptions rather than raw filenames/sizes.
   - All download endpoints route through `ConferenceFileStorage::download()` which provides `Content-Disposition: attachment` (via Supabase signed URL `download` parameter or Laravel binary download response) to prevent in-browser PDF hijack.
   - When papers are completed, an **Editorial Completion Banner** informs authors that the final manuscript was submitted to EDAS on their behalf.
4. **Non-Destructive File Versioning & Dynamic Final Locks**:
   - Deleting a `Final` version soft-deletes the row; the author portal and staff views safely fall back to the highest available version with a `Latest` badge until a new `Final` is explicitly designated.
5. **Staff Submission Details Editor with Original Identifier Preservation**:
   - Admins can edit paper codes, titles, and author details on `show.blade.php`.
   - Database preserves `original_paper_code`, `original_title`, and `original_author_email` so future CSV bulk imports or Google Form Webhooks automatically match the existing paper record.
6. **E.164 Multi-Country Phone Normalization**:
   - Handled via `App\Services\PhoneNumber::parse()`. Indonesian numbers (`08...`, `8...`) automatically normalize to `+628...`, while preserving international country codes (`+60`, `+65`, `+1`, etc.).

---

## 6. Language & Copy Conventions (CRITICAL)

- **Internal Staff UI (Dashboard, Papers, Conferences, Monitoring)**: Written in clear, professional **Indonesian** (e.g. *"Daftar Paper"*, *"Tugaskan PIC"*, *"Riwayat Status"*, *"Simpan Perubahan"*).
- **Public Author Interfaces (Submit Form, Author Portal, User Manuals)**: Written in formal, polite **English** (e.g. *"Submission Details"*, *"Editorial Compliance Checklist Monitoring"*, *"Download Editable Manuscript"*).
- **Code Identifiers & Database Schema**: 100% **English** (classes, methods, table columns, routes, enums, commit messages).

---

## 7. Standard Verification Sequence

Always execute this verification suite before completing any user request:

```bash
# 1. Run full PHPUnit / Pest feature test suite (127 tests)
php artisan test

# 2. Compile and optimize production frontend assets with Vite
pnpm build

# 3. Clear and verify Blade view caches if templates changed
php artisan view:cache

# 4. Standard Git workflow (work on agent/build-paperflow, merge to main)
git add .
git commit -m "type(scope): concise description of changes"
git push origin agent/build-paperflow
git checkout main
git merge agent/build-paperflow
git push origin main
git checkout agent/build-paperflow
```

---

## 8. Potential Next Roadmap Ideas (Context for New LLM)

If the user asks about upcoming feature plans:
- **Automated Revision Deadline Reminder**: Guarded delayed queue job or Laravel Scheduler command (`php artisan paperflow:send-reminders`) to send automated reminder emails at H-3 and H-1 before `deadline_at`.
- **AI-Powered Editorial Revision Note Translator**: Endpoint (`POST /papers/{submission}/ai/translate-note`) using Google Gemini / OpenAI to translate Indonesian editor notes into academic IEEE revision instructions with one click.
- **Enhanced CSV Export Customizer**: Configurable column selector for conference CSV paper exports.
