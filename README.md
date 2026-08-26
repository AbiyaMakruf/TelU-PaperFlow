# Paperflow — Academic Conference Editorial Workflow Platform

[![Production Live](https://img.shields.io/badge/Production-https%3A%2F%2Fpaperflow.info-10b981?style=for-the-badge&logo=googlechrome&logoColor=white)](https://paperflow.info)
[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP 8.2](https://img.shields.io/badge/PHP-^8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Tailwind CSS 4](https://img.shields.io/badge/Tailwind-v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![PostgreSQL 17](https://img.shields.io/badge/PostgreSQL-17-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)](https://postgresql.org)

Paperflow is an enterprise academic conference editorial workflow application built on Laravel 12. It replaces fragmented Google Forms and static spreadsheets with conference-scoped public submission forms, staff assignments, editorial/reviewer checklists, manuscript versioning, author communication, progress tracking, EDAS handoff, CSV reconciliation, and operational monitoring.

> **Production Deployment:** Access the live application at [**https://paperflow.info**](https://paperflow.info).
>
> **AI/Coding Agents:** Read [`AGENTS.md`](AGENTS.md) first for architecture, workflow rules, security constraints, and the verification baseline.

---

## 📸 Website & Interface Preview

![Paperflow Application Interface Preview](public/paperflow-preview.svg)

---

## 🌟 Key Features & Capabilities

- **GCP-Style Workspace Selector:** Header and drawer conference selector scoping all visible submissions, workload matrices, and audit logs to `session('active_conference_id')`.
- **Interactive Chart.js Dashboard Analytics:** 3 visual dashboard charts including a 14-day continuous submission trend line with smooth gradient area fill, paper status ratio doughnut chart, and Person in Charge (PIC) Editor/Reviewer workload & turnaround bar chart.
- **16-Point IEEE Editorial Compliance Checklist:** Interactive compliance checklist with guidance accordions, automated revision feedback template generation, and dynamic item re-indexing to prevent PostgreSQL 32-bit `sort_order` overflow crashes.
- **Private Tokenized Author Portal:** Passwordless portal (`/submission/access/{token}`) providing authors with live checklist monitoring, submission detail updates, editable revision DOCX/ZIP uploads, and PDF guidance file downloads.
- **IEEE PDF eXpress & EDAS Management:** Editorial uploads and can replace the verified IEEE PDF eXpress file; Reviewer uploads it to EDAS, records structured warnings, returns corrections when needed, and marks the paper completed only after EDAS is confirmed.
- **Conference-Scoped EDAS Reconciliation:** Sub-navigation tool (`/conferences/{id}/edas-reconciliation`) imports EDAS Paper ID, Title, and a dynamic semicolon-separated author list, with expandable author rows and matching/export support.
- **Workflow Email Design:** Branded HTML email cards standardize Paper ID/title across assignment, review, EDAS return/completion, revert, and author-revision workflow notifications. EDAS warning logs are included only when present.
- **Superadmin Password-Protected Clean Slate System Purge:** Dedicated development reset tool (`POST /admin/system/purge`) requiring Superadmin password verification to wipe all non-superadmin database records and physical file storage while keeping active Superadmin accounts intact.
- **Dual File Storage Architecture:** Choose per conference between private Supabase Storage (signed URLs) or an authorized Google Drive folder, complete with temporary local upload attempt retry mechanisms.
- **E.164 Multi-Country Phone Parsing & WhatsApp Integration:** International phone number parsing with mutator normalization (`+628...`) and direct `wa.me` click-to-chat integration with pre-filled checklist revision drafts.
- **Ultrawide & 34-Inch Display Optimization:** Constrained main layout container (`max-w-[1600px]`) and page containers (`max-w-[1400px]`) ensuring balanced presentation on 34-inch ultrawide (3440px / 21:9) monitors.
- **Role-Scoped User Documentation:** Public standalone author manual (`/user-manual/author`) and authenticated staff documentation hub (`/user-manual/{role}`) covering Superadmin, Conference Admin, Editorial, Reviewer, and Viewer roles.

---

## 🔒 Role-Based Authorization & Permissions

Staff authenticate using a single login page (`/login`) accepting either username or email. First-time login forces email entry and password change from the default initial password (`user1234`).

| Role | Primary Responsibilities & Access Scope |
| :--- | :--- |
| **Superadmin** | Creates users and conferences, manages global data, monitors system status/logs, executes password-protected system purges, and impersonates staff accounts. |
| **Conference Admin** | Configures conference settings, form builder, team memberships, email templates, storage providers, branding, and EDAS reconciliation. |
| **Editorial** | Restricted to assigned papers. Completes 16 IEEE compliance checklists, issues revision requests, generates feedback, and manages manuscript file versions. |
| **Reviewer** | Restricted to assigned papers. Manages IEEE PDF eXpress status, logs EDAS errors, uploads verified camera-ready PDFs, and issues final approvals. |
| **Viewer** | Read-only access to conference progress and analytics. |

---

## 🛠️ Technology Stack & Dependencies

- **Backend Framework:** Laravel 12 (`PHP ^8.2`; `ext-zip` required for DOCX preview extraction)
- **Database:** PostgreSQL 17 via Supabase Session Pooler (`pdo_sqlite` used for automated local tests)
- **Frontend Stack:** Blade, Alpine.js (with Collapse plugin), Tailwind CSS 4, Vite
- **Queue & Mail Services:** Database queue driver, queued branded HTML email via Gmail SMTP
- **Security & Protection:** Cloudflare Turnstile CAPTCHA, named rate limiters (`public-submission`, `author-revision`), and strict Eloquent policy authorization.

---

## 🚀 Quick Start & Local Setup

### Prerequisites
- PHP `^8.2` with `fileinfo`, `mbstring`, `openssl`, `pdo_sqlite`, `pdo_pgsql`, and `zip` extensions enabled.
- Composer, Node.js, and pnpm 11.x.

### 1. Clone & Install Dependencies
```bash
git clone https://github.com/AbiyaMakruf/TelU-PaperFlow.git
cd TelU-PaperFlow
composer install
pnpm install
```

### 2. Environment & Encryption Key Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Migration & Asset Build
Ensure `database/database.sqlite` exists (or configure PostgreSQL in `.env`), then run:
```bash
php artisan migrate
pnpm build
php artisan storage:link
```

### 4. Create Initial Superadmin Account
```bash
php artisan paperflow:make-superadmin admin --email=admin@example.com --name="Super Admin"
```

### 5. Launch Development Server & Queue Worker
Run the application server and background queue worker in separate terminal windows:
```bash
# Terminal 1: Application Server
php artisan serve

# Terminal 2: Background Queue Worker
php artisan queue:work --tries=3
```

---

## 🧪 Demo User Accounts (Development Only)

To populate a demo conference (`paperflow-demo`) with sample paper submissions and test accounts across all roles, set `PAPERFLOW_DEMO_PASSWORD` in `.env` (minimum 8 characters) and run:

```bash
php artisan db:seed --class=Database\\Seeders\\DemoUsersSeeder
```

This creates the following test accounts using your configured `PAPERFLOW_DEMO_PASSWORD`:
- **Superadmin:** `superadmin@paperflow.test`
- **Conference Admin:** `admin@paperflow.test`
- **Editorial PIC:** `editorial@paperflow.test`
- **Reviewer PIC:** `reviewer@paperflow.test`
- **Viewer:** `viewer@paperflow.test`

*Do not run the demo seeder on a production environment.*

---

## ⚙️ Environment Configuration Reference (`.env`)

Copy `.env.example` to `.env`. Sensitive credentials and secret keys must remain empty or configured securely on your server environment.

```dotenv
APP_NAME=Paperflow
APP_ENV=production
APP_DEBUG=false
APP_URL=https://paperflow.info

# PostgreSQL / Supabase Session Pooler
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.YOUR_PROJECT_REF
DB_PASSWORD=YOUR_DATABASE_PASSWORD
DB_SSLMODE=require

# Supabase File Storage (Server-Only Access)
PAPERFLOW_STORAGE_DRIVER=supabase
SUPABASE_URL=https://YOUR_PROJECT_REF.supabase.co
SUPABASE_SECRET_KEY=YOUR_SUPABASE_SERVICE_ROLE_KEY
SUPABASE_STORAGE_BUCKET=paperflow-private

# Google Drive Integration
GOOGLE_CLIENT_ID=YOUR_GOOGLE_CLIENT_ID
GOOGLE_CLIENT_SECRET=YOUR_GOOGLE_CLIENT_SECRET
GOOGLE_REDIRECT_URI=https://paperflow.info/google-drive/callback
GOOGLE_DRIVE_FOLDER_NAME="{conference}"

# Cloudflare Turnstile CAPTCHA Protection
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=YOUR_TURNSTILE_SITE_KEY
TURNSTILE_SECRET_KEY=YOUR_TURNSTILE_SECRET_KEY

# Queue & Mail Setup (Gmail SMTP)
QUEUE_CONNECTION=database
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=paperflowadmin@gmail.com
MAIL_PASSWORD=YOUR_GOOGLE_APP_PASSWORD
MAIL_FROM_ADDRESS=paperflowadmin@gmail.com
MAIL_FROM_NAME="Paperflow"
```

> **Security Note:** Never expose `SUPABASE_SECRET_KEY`, `GOOGLE_CLIENT_SECRET`, database passwords, or Gmail App Passwords in client-side code, git commits, or `VITE_*` variables.

---

## 🚢 Production Deployment Procedure

When deploying updates to production hosting (e.g. `paperflow.info`):

```bash
# 1. Pull latest code and update dependencies
git pull origin main
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile
pnpm build

# 2. Run database migrations
php artisan migrate --force

# 3. Clear and optimize caches
php artisan optimize:clear
php artisan optimize
php artisan storage:link

# 4. Restart background queue worker
php artisan queue:restart
```

Ensure a persistent supervisor or process manager keeps `php artisan queue:work --tries=3` running continuously for email delivery and background jobs. On shared Hostinger plans without a process manager, configure a Cron Job to invoke `/bin/sh /home/u374025150/domains/paperflow.info/public_html/scripts/paperflow-queue-worker.sh` at the required interval. The script locks execution, uses PHP 8.2, and exits when the ready queue is empty; adapt the absolute path for other deployments.

---

## 🔍 Quality Assurance & Testing

Run the automated test suite and code formatting checks before committing changes:

```bash
# Code Style Inspection
vendor/bin/pint --test

# Run Full Automated Test Suite (109+ Tests)
php artisan test

# Rebuild Vite Frontend Assets
pnpm build
```

---

## 📄 License & Attribution

Paperflow is developed for academic conference editorial management under the **TelU PaperFlow** ecosystem. All rights reserved.
