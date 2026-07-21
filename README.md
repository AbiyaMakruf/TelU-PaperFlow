# Paperflow

Paperflow adalah workspace editorial conference berbasis Laravel. Aplikasi ini menggantikan Google Form dan spreadsheet terpisah dengan form per conference, assignment PIC, checklist editorial/reviewer, versioning file, email author, timeline, statistik, dan ekspor CSV.

## Fitur

- Satu login staf dengan UI dan izin sesuai role: superadmin, conference admin, editorial, reviewer, dan viewer.
- Conference dan slug form publik yang dapat diduplikasi.
- Form builder, checklist editorial/reviewer, dan template email per conference.
- Submission author tanpa akun melalui `/{slug-conference}`.
- Portal author bertoken untuk status, feedback, download, dan upload revisi.
- File private di Supabase Storage; database hanya menyimpan object path dan checksum.
- Validasi submission, assignment editor/reviewer, checklist per siklus, feedback internal/author, serta alur EDAS.
- Dashboard personal, statistik conference, audit log, email log, dan export CSV sesuai scope role.

## Stack

- PHP 8.2+ dan Laravel 12
- PostgreSQL 17 di Supabase
- Private Supabase Storage
- Blade, Alpine.js, Tailwind CSS 4, dan Vite
- Laravel Queue dan Gmail SMTP

## Setup lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
pnpm install
pnpm build
php artisan paperflow:make-superadmin admin@example.com --name="Super Admin"
php artisan serve
```

Untuk pengembangan lokal, `DB_CONNECTION=sqlite`, `PAPERFLOW_STORAGE_DRIVER=local`, `MAIL_MAILER=log`, dan `QUEUE_CONNECTION=database` sudah memadai. Jalankan worker queue di terminal terpisah:

```bash
php artisan queue:work --tries=3
```

## Konfigurasi Supabase production

Proyek yang tersambung adalah `paperflow` (`rbwkivxgmadvtlcefrie`). Skema aplikasi dan indeks sudah diterapkan. Isi konfigurasi server berikut dari Supabase Dashboard; jangan commit `.env`.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://paperflow.id

DB_CONNECTION=pgsql
DB_URL=null
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.rbwkivxgmadvtlcefrie
DB_PASSWORD=your-database-password
DB_SSLMODE=require

PAPERFLOW_STORAGE_DRIVER=supabase
SUPABASE_URL=https://rbwkivxgmadvtlcefrie.supabase.co
SUPABASE_SECRET_KEY=sb_secret_...
SUPABASE_STORAGE_BUCKET=paperflow-private
```

Buat bucket bernama `paperflow-private` melalui **Storage > New bucket** dengan pengaturan:

- Public bucket: **off**
- File size limit: **25 MB**
- MIME yang diizinkan: DOC, DOCX, TEX/plain text, ZIP, dan PDF

Secret key hanya dipakai Laravel di server untuk upload dan signed URL singkat. Jangan memakai publishable/anon key untuk `SUPABASE_SECRET_KEY`, jangan membuat bucket public, dan jangan menaruh secret dalam variabel `VITE_*`.

Seluruh tabel aplikasi menggunakan RLS tanpa policy untuk anon/authenticated karena browser tidak berkomunikasi langsung dengan Data API. Koneksi PostgreSQL Laravel harus memakai kredensial server yang memiliki hak database yang sesuai.

## Gmail SMTP

Aktifkan 2-Step Verification pada `paperflowadmin@gmail.com`, buat Google App Password, lalu isi:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=paperflowadmin@gmail.com
MAIL_PASSWORD=app-password-16-karakter
MAIL_FROM_ADDRESS=paperflowadmin@gmail.com
MAIL_FROM_NAME=Paperflow
QUEUE_CONNECTION=database
```

Worker queue wajib berjalan terus di production (Supervisor, systemd, atau process manager hosting):

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## Deploy

Urutan deploy yang direkomendasikan:

```bash
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile
pnpm build
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

Document root web server harus menunjuk ke `public/`. Pastikan `storage/` dan `bootstrap/cache/` writable, HTTPS aktif, serta worker queue direstart setelah deploy.

## Operasi awal

1. Buat superadmin pertama dengan `paperflow:make-superadmin`.
2. Login dan ganti password sementara.
3. Buat conference, atur slug/tanggal, form, checklist, template email, dan anggota.
4. Publish form lalu ubah status conference menjadi aktif.
5. Uji satu submission dan pastikan email, signed download, dan queue worker berjalan.

## Akun demo per role

Untuk environment development, isi `PAPERFLOW_DEMO_PASSWORD` di `.env` dengan password minimal 12 karakter, lalu jalankan:

```bash
php artisan db:seed --class=Database\\Seeders\\DemoUsersSeeder
```

Seeder bersifat idempotent dan membuat conference `paperflow-demo` beserta akun berikut:

- `superadmin@paperflow.test`
- `admin@paperflow.test`
- `editorial@paperflow.test`
- `reviewer@paperflow.test`
- `viewer@paperflow.test`

Seluruh akun menggunakan nilai `PAPERFLOW_DEMO_PASSWORD`. Jangan menjalankan seeder akun demo pada production.

## Quality checks

```bash
vendor/bin/pint --test
php artisan test
pnpm build
```

Tes mencakup autentikasi, isolasi role per conference, konfigurasi admin, submission publik, portal author, penyimpanan lokal pengganti Supabase pada test, checklist wajib, workflow editorial/reviewer/EDAS, template email, superadmin bootstrap, dan export CSV.
