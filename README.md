# TPMS — Student Module (Laravel + PostgreSQL + private file storage)

Backend for **Section 4 (Student Information Management)** of the TPMS v2 requirements.
Copy this folder over a fresh Laravel 11/12 project (it is an overlay: `app/`, `config/`, `database/`, `routes/`, `tests/`).

> **Status:** every PHP file passes `php -l`. The code has **not been executed** against a real
> Laravel + PostgreSQL install yet (no Composer/Packagist in the build sandbox). Run the test suite first
> (step 7) and expect to fix a few small things.

## 1. Setup

```bash
composer create-project laravel/laravel tpms && cd tpms
php artisan install:api                 # Sanctum + routes/api.php (personal_access_tokens migration)
# copy this overlay into the project (overwrite app/Models/User.php and routes/api.php)
```

`.env`
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tpms
DB_USERNAME=tpms
DB_PASSWORD=...
TPMS_DOCUMENT_DISK=tpms_local          # or tpms_s3
TPMS_DOCUMENT_MAX_KB=5120
TPMS_SEED_PASSWORD=choose-a-strong-one
```

Keep Laravel's default users migration: migration `...000002` extends it (its `0001_01_01_...` prefix makes it run first).

`bootstrap/app.php` — register the role middleware alias:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
})
```

`config/filesystems.php` — add two PRIVATE disks:
```php
'tpms_local' => [
    'driver' => 'local',
    'root' => storage_path('app/private/tpms'),   // outside public/, not symlinked
    'visibility' => 'private',
    'throw' => true,
],
'tpms_s3' => [                                     // S3 / MinIO / Wasabi / any S3-compatible store
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'ap-south-1'),
    'bucket' => env('AWS_BUCKET'),
    'endpoint' => env('AWS_ENDPOINT'),             // set for MinIO
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'visibility' => 'private',
    'throw' => true,
],
```
(`composer require league/flysystem-aws-s3-v3 "^3.0"` only if you use S3/MinIO.)

```bash
php artisan migrate
php artisan db:seed                                  # departments, branches, admin, TPO, one coordinator per dept
php artisan tpms:import-master-list roster.csv --batch=2026-odd
php artisan test
```

Roster CSV header:
`university_id,full_name,institutional_email,department_code,branch_code,admission_type,admission_year,graduation_year,current_semester`

## 2. Storage architecture (one file-storage system)

```
upload ──► DocumentService
            ├─ checks real content type (finfo), extension, size; renames to a UUID
            ├─ bytes    ► private disk : students/{student_id}/{type}/{uuid}.{ext}
            └─ metadata ► documents table : status, version, sha256, reviewer, reason
download ► GET /api/documents/{uuid}/download ► DocumentPolicy ► stream (never a public URL)
```
- Switching from server disk to MinIO/S3 is one env var (`TPMS_DOCUMENT_DISK`); no code changes.
- Re-upload = new `documents` row (`version+1`, `replaces_id`); the old row becomes `SUPERSEDED`. Files are never deleted.
- If the DB write fails after the file is stored, the orphan file is removed.
- Back up the DB and the storage bucket together (a DB row without its file is a broken record).

## 3. Tables (PostgreSQL)

| Table | Purpose |
|---|---|
| `departments`, `branches` | reference data |
| `users` (+`role`, `department_id`, `is_active`) | all accounts; coordinator must have a department (CHECK) |
| `student_master_list` | imported roster; registration only for unclaimed IDs |
| `students` | sections A, B, D, F (identity, contact, skills/links, preferences) |
| `academic_records` | 10th / 12th / Diploma / Semester N rows with review lifecycle |
| `experiences` | internships/jobs with review lifecycle |
| `documents` | file metadata, versions, review state |
| `audit_logs` | append-only (DB trigger blocks UPDATE/DELETE) |
| `notifications` | in-app decisions shown to students |

Database-level guarantees (not just PHP validation): CHECK constraints for levels/ranges/status;
**one VERIFIED row per (student, level, semester)**; **one open row per key**; one primary resume per student;
a record can only reference a document of the **same student** (composite FK); lateral students cannot be below semester 3;
a VERIFIED/PENDING row must have its document. Schema diagram source: `docs/schema.dbml` (paste into dbdiagram.io).

## 4. Lifecycle rules implemented

```
create ─► DRAFT ─(marksheet)─► submit ─► PENDING ─► VERIFIED (locked if past term)
                                           └─► REJECTED ─(fix / re-upload)─► submit ─► PENDING
VERIFIED + edit ─► NEW row (DRAFT, supersedes_id) ─► ... ─► on approval old row = SUPERSEDED
locked VERIFIED ─► edit refused (423) until the coordinator unlocks it (reason is audited)
```
- Eligibility/reports read **only** `VerifiedAcademicProfileService` → `status = 'VERIFIED'` rows.
- `DATA_MISMATCH` rejection blames the numbers, so the same marksheet can be reused; any other reason rejects the file too.
- Experience without certificate = `SELF_DECLARED` (visible, must be labelled unverified).
- Resume: first upload becomes primary; replacing an **approved** resume keeps the old one primary until the new one is approved.
- Lateral entry: Diploma allowed/required, semesters start at 3, 12th optional. Regular: 10th + 12th + semesters from 1.
- Profile completeness is computed on demand; `can_apply` = identity confirmed + required academics verified + primary resume approved.

## 5. API

| Area | Endpoints |
|---|---|
| Auth | `POST /api/auth/register`, `/login`, `/logout`; `GET /api/me` |
| Student | `GET/PATCH /api/student/profile` · `GET/POST /api/student/academic-records` · `PATCH/DELETE …/{id}` · `POST …/{id}/document` · `POST …/{id}/submit` · same shape for `/experiences` (`…/{id}/certificate` attaches **and** submits) · `GET/POST /api/student/documents` (resume, other) · `GET /api/student/notifications` |
| Coordinator (own dept) | `GET /api/coordinator/students[/{id}]` · `GET /api/coordinator/verification-queue` · `POST academic-records/{id}/approve|reject|unlock` · `experiences/{id}/approve|reject` · `documents/{uuid}/approve|reject` |
| TPO (read-only) | `GET /api/tpo/students[/{id}]` (filters: department_id, branch_id, semester, admission_type, search, only_pending) |
| TPO / Admin | `POST /api/master-list/import` (CSV) |
| Files | `GET /api/documents/{uuid}/download` |

Reject body: `{"reason_code": "DATA_MISMATCH|UNREADABLE|WRONG_DOCUMENT|INCOMPLETE|INVALID_OR_EXPIRED|OTHER", "reason": "required for OTHER"}`.

## 6. Security decisions
- Policies: owner / same-department coordinator / TPO (read) — **System Admin gets no student data**.
- Other department's records return **404**, not 403.
- Student's own records are fetched through their relation, so guessing another student's id gives 404.
- Identity/admission columns are never mass-assignable from a request.
- Views of someone else's document are audit-logged. Registration errors are deliberately generic.
- Passwords: min 10, mixed case + number; login/register throttled. Add email verification (`MustVerifyEmail`) before go-live.

## 7. Known gaps / decisions for you
- Not yet run: execute `php artisan test` on PostgreSQL (the tests use `Storage` disk `tpms_test` and Sanctum).
- CGPA = printed CGPA of the latest verified semester, else mean of SGPAs (credit-weighted needs subject credits).
- Correction requests are simplified to "coordinator unlocks" (as in the MVP scope).
- Eligibility engine, drives, applications and placement records are outside this module; they should call `VerifiedAcademicProfileService`.
- Virus scanning of uploads (ClamAV) and S3 server-side encryption are not configured.
