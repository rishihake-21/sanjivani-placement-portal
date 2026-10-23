# Sanjivani Placement Portal (TPMS)

![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-database-4169E1?logo=postgresql&logoColor=white)
![Tailwind CSS 4](https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white)

A Training & Placement Management System for Sanjivani University. It takes a student from roster import and registration, through department-level verification of academic records, to eligibility-checked drive applications, recruitment rounds, and TPO-verified placement records.

Built with **Laravel 12**, **PostgreSQL**, Blade views, and a private document store. The same controllers serve a session-based web portal and a token-based JSON API (`/api/*`, Laravel Sanctum).

## Contents

- [What it does](#what-it-does)
- [Roles](#roles)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Configuration](#configuration)
- [Seeded accounts](#seeded-accounts)
- [Artisan commands and scheduler](#artisan-commands-and-scheduler)
- [Project layout](#project-layout)
- [Routes and API](#routes-and-api)
- [Data model and guarantees](#data-model-and-guarantees)
- [Document storage](#document-storage)
- [Testing](#testing)
- [Known limitations and roadmap](#known-limitations-and-roadmap)

## What it does

**Student information, with verification**
- Students register only if their university ID is in the imported master list and still unclaimed. Department, branch, admission type, and semester come from the roster, never from the form.
- Students maintain a profile, academic records (10th, 12th, Diploma, Semester N), experiences (internships and jobs), and documents (resume, marksheets, certificates).
- Records move through a review lifecycle: `DRAFT → PENDING → VERIFIED` or `REJECTED`. A verified record that is edited creates a new row, and the old one becomes `SUPERSEDED` only after approval. Verified records from past terms are locked until a coordinator unlocks them, and the reason is audited.
- Coordinators can reject with a coded reason (`DATA_MISMATCH`, `UNREADABLE`, `WRONG_DOCUMENT`, `INCOMPLETE`, `INVALID_OR_EXPIRED`, `OTHER`) and can ask a student to re-upload a document.
- Regular and lateral-entry students are both supported (lateral: Diploma required, semesters start at 3, 12th optional).

**Placement drives and recruitment**
- The TPO manages companies and drives (`DRAFT → PUBLISHED → CLOSED` or `CANCELLED`) with eligibility criteria: graduation year, target branches, and academic thresholds.
- An **eligibility engine** evaluates student × drive using **verified academic values only**, returns a reason for every failed or unverified criterion, and is re-evaluated when a student views a drive and again when they apply. Already-placed students are excluded.
- Eligible students are notified through a queued job. Students apply (rate-limited) and can withdraw. Applying also requires a confirmed identity and an approved primary resume.
- Applications move through stages: `APPLIED → SHORTLISTED → APTITUDE → TECHNICAL → HR → SELECTED → OFFER_RECEIVED → PLACED`, plus `REJECTED` and `WITHDRAWN`. The TPO can change stages one at a time or in bulk for a drive.
- Offers become official placements (`OFFERED → PLACED` or `DECLINED`) once the TPO verifies them.
- Drives past their application deadline are closed automatically by an hourly scheduled command.

**Reporting**
- TPO: university-wide dashboard, department, branch, and company reports, and CSV exports of students and placements.
- Coordinators: a dashboard, branch summary, and CSV exports limited to their own department.

## Roles

| Role | Scope |
|---|---|
| `student` | Own profile, records, documents, notifications, drives, applications, placements |
| `tp_coordinator` | Verification queue, students, drives, applications, and placements for **their own department only** (other departments return 404) |
| `tpo` | University-wide, read access to students; manages companies, drives, applications, placements, reports; imports the master list |
| `system_admin` | Manages coordinator accounts and can import the master list. **Gets no student data.** |
| `hod`, `recruiter` | Defined in the `Role` enum but reserved for future use |

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP ^8.2, Laravel ^12.0 |
| Auth | Laravel session auth for the web portal, Laravel Sanctum ^4.3 (token or stateful session) for `/api` |
| Database | PostgreSQL (the migrations rely on CHECK constraints, triggers, partial unique indexes, and `jsonb`) |
| Frontend | Blade templates, Tailwind CSS 4 via Vite 6, and plain JavaScript helpers in `public/js` |
| Queue, cache, session | Database drivers by default |
| File storage | Private Laravel disk (local by default, S3-compatible optional) |
| Tests | PHPUnit ^11.5 |

## Getting started

### Prerequisites

PHP 8.2+ (with `pdo_pgsql` and `fileinfo`), Composer, Node.js and npm, and a running PostgreSQL server.

### Install

```bash
git clone https://github.com/rishihake-21/sanjivani-placement-portal.git
cd sanjivani-placement-portal

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Edit `.env` (see [Configuration](#configuration)). `.env.example` defaults to SQLite, so you must switch it to PostgreSQL before migrating. Then:

```bash
createdb tpms                                   # or create the database however you prefer

php artisan migrate
TPMS_SEED_PASSWORD='choose-a-strong-one' php artisan db:seed
```

### Run

```bash
composer dev
```

This runs the PHP server, a queue listener, the log tailer (Pail), and the Vite dev server together. Open <http://localhost:8000>.

Prefer to run pieces yourself:

```bash
php artisan serve
php artisan queue:work          # needed for eligible-student notifications
php artisan schedule:work       # needed to auto-close expired drives
npm run dev                     # or: npm run build
```

### Import the student roster

Registration is closed until a roster is loaded:

```bash
php artisan tpms:import-master-list roster.csv --batch=2026-odd
```

CSV header:

```
university_id,full_name,institutional_email,department_code,branch_code,admission_type,admission_year,graduation_year,current_semester
```

The import is all-or-nothing: if any row fails validation, nothing is imported. The TPO and system admin can also upload the CSV through `POST /api/master-list/import`.

## Configuration

Key `.env` settings:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tpms
DB_USERNAME=tpms
DB_PASSWORD=

# Uploaded documents
TPMS_DOCUMENT_DISK=tpms_local       # must be a private disk
TPMS_DOCUMENT_MAX_KB=5120

# Required by `php artisan db:seed` (it refuses to run without it)
TPMS_SEED_PASSWORD=

QUEUE_CONNECTION=database
MAIL_MAILER=log                     # switch to a real mailer in production
```

Document rules live in `config/tpms.php`: PDF, JPEG, and PNG only, checked against the **file content** (not the client-supplied name or header), 5 MB by default. Views of someone else's document by `tp_coordinator`, `tpo`, or `hod` are written to the audit log.

## Seeded accounts

`php artisan db:seed` creates departments and branches (CSE incl. CSE-AIML, IT, ECE, ME, CE), then:

| Account | Email |
|---|---|
| System admin | `admin@tpms.local` |
| TPO | `tpo@tpms.local` |
| Coordinator, one per department | `cse.coordinator@tpms.local`, `it.coordinator@tpms.local`, `ece.coordinator@tpms.local`, `me.coordinator@tpms.local`, `ce.coordinator@tpms.local` |
| Demo students | e.g. `rishi.kumar@sanjivani.edu.in`, `arjun.sharma@sanjivani.edu.in` |

Staff passwords come from `TPMS_SEED_PASSWORD`. The demo-student seeders use the same variable and fall back to `password` when it is unset, so **never seed with the fallback on a shared or production database**. The seeders also create demo companies, drives, and fully completed student profiles.

## Artisan commands and scheduler

| Command | Purpose |
|---|---|
| `php artisan tpms:import-master-list {file} {--batch=}` | Import or refresh the student roster from CSV (all-or-nothing) |
| `php artisan tpms:close-expired-drives` | Close published drives whose application deadline has passed |

`tpms:close-expired-drives` runs **hourly** (registered in `TpoServiceProvider`, no overlap). In production, add the standard Laravel scheduler cron entry:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Project layout

```
app/
  Console/Commands/       master-list import, close expired drives
  Enums/                  Role, ApplicationStage, DriveStatus, PlacementStatus, DocumentStatus, ...
  Http/
    Controllers/
      Student/ Coordinator/ Tpo/ Admin/   API controllers per role
      Web/                                page controllers that render the Blade views
    Middleware/EnsureRole.php             `role:` route middleware
    Requests/ Resources/                  validation and JSON shaping
  Jobs/NotifyEligibleStudents.php
  Models/ Policies/ Notifications/
  Services/               EligibilityEngine, ApplicationService, DriveService, PlacementService,
                          VerificationService, DocumentService, RegistrationService, MasterListImporter, ...
  Support/                stage transition rules, CSV-injection-safe export helper, presenters
config/tpms.php           document storage and audit settings
database/
  migrations/ seeders/ factories/
docs/schema.dbml          schema diagram source (paste into dbdiagram.io)
resources/views/          layouts, components, and student / coordinator / tpo / auth pages
routes/                   web.php, api.php, tpo.php, coordinator.php
tests/                    Feature tests for the student, coordinator, and TPO modules
```

`architecture.md`, `page-map.md`, `request-flow.md`, and `architecture.mmd` are reference notes from a passive review of the university's existing UMS login page. They describe that external system, not this application.

## Routes and API

The portal's form submissions and the JSON API share the same controllers.

- **Web (session + CSRF):** pages live under `/student`, `/coordinator`, `/tpo`, and `/admin`. Every write endpoint sits under an `/x/` prefix (for example `POST /student/x/academic-records`), so it is never confused with the JSON API.
- **API (`/api`, Sanctum):**

| Area | Highlights |
|---|---|
| Auth | `POST /api/auth/register`, `/login`, `/logout` (throttled), `GET /api/me` |
| Student | profile, `academic-records`, `experiences`, `documents`, `notifications`, `drives`, `drives/{id}/apply`, `applications`, `applications/{id}/withdraw`, `placements`, `reupload-requests` |
| Coordinator | `students`, `verification-queue`, approve, reject, and unlock for academic records, approve and reject for experiences and documents, `drives`, `applications`, `placements`, `reupload-requests`, dashboard and CSV reports |
| TPO | `students`, `companies`, `drives` (create, update, publish, close, cancel), `drives/{id}/eligible-students`, `applications` (stage, offer, bulk-stage), `placements` (create, verify, decline), dashboard and reports |
| Admin | `coordinators` (create, update, deactivate, reactivate), `POST /api/master-list/import` (TPO or admin) |
| Files | `GET /api/documents/{uuid}/download` |

Reject requests take `{"reason_code": "...", "reason": "required for OTHER"}`. Run `php artisan route:list` for the full list.

## Data model and guarantees

Main tables: `departments`, `branches`, `users`, `student_master_list`, `students`, `academic_records`, `experiences`, `documents`, `coordinators`, `companies`, `placement_drives`, `drive_eligibility`, `drive_branches`, `applications`, `application_status_history`, `placements`, `reupload_requests`, `audit_logs`, and `notifications`.

Rules are enforced in the database, not only in PHP validation:

- CHECK constraints for levels, ranges, and statuses
- One `VERIFIED` academic row per (student, level, semester), and one open row per key
- One primary resume per student
- A record can only reference a document belonging to the same student (composite foreign key)
- A coordinator must have a department
- Lateral students cannot be below semester 3
- A `VERIFIED` or `PENDING` row must have its document
- `audit_logs` is append-only (a trigger blocks UPDATE and DELETE)

Eligibility and reports read **only** `VERIFIED` academic data (through `VerifiedAcademicProfileService`). An experience without a certificate is stored as `SELF_DECLARED` and must be shown as unverified.

## Document storage

```
upload ─► DocumentService
           ├─ verifies real content type (finfo), extension, and size; renames to a UUID
           ├─ bytes    ─► private disk : students/{student_id}/{type}/{uuid}.{ext}
           └─ metadata ─► documents table: status, version, sha256, reviewer, reason
download ─► GET .../documents/{uuid}/download ─► DocumentPolicy ─► streamed response (never a public URL)
```

- A re-upload creates a new `documents` row (`version + 1`) and marks the old one `SUPERSEDED`. Files are never deleted.
- If the database write fails after the file is stored, the orphaned file is removed.
- Back up the database and the storage location together. A row without its file is a broken record.

Other security choices: policies limit access to the owner, same-department coordinators, or the TPO; guessing another student's record id returns 404; identity and admission columns are never mass-assignable; registration errors are intentionally generic; registration requires a password of 10+ characters with mixed case and a number; the API login and register endpoints are throttled (6 per minute) and so is applying to a drive (20 per minute).

## Testing

```bash
php artisan test
```

Feature tests cover the student, coordinator, and TPO modules, using a faked private storage disk. The tests use `RefreshDatabase`, which **wipes the database**, and `phpunit.xml` does not pin a database connection, so they run against whatever `DB_*` settings your environment provides. Point them at a **dedicated PostgreSQL test database** (for example by setting `DB_DATABASE=tpms_test` in the environment), never your development data.

## Known limitations and roadmap

- **Admin dashboard view is missing.** `/admin/dashboard` renders `admin.dashboard`, but `resources/views/admin/` does not exist yet.
- **Forgot password is a stub.** The route renders `auth.forgot-password`, which has no view yet, and the `POST` handler has no logic.
- **S3 / MinIO disk is not configured.** `config/tpms.php` mentions `tpms_s3`, but only `tpms_local` is defined in `config/filesystems.php`. Add the disk (and `composer require league/flysystem-aws-s3-v3`) before setting `TPMS_DOCUMENT_DISK=tpms_s3`.
- Email verification (`MustVerifyEmail`) is not enabled and should be added before go-live.
- Virus scanning of uploads (for example ClamAV) is not set up.
- CGPA is the printed CGPA of the latest verified semester, or else the mean of SGPAs. Credit-weighted CGPA needs subject credits.
- Corrections to locked records are handled by a coordinator unlock rather than a separate request workflow.
- The `hod` and `recruiter` roles are reserved and have no portal yet.
