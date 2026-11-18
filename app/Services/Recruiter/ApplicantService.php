<?php

namespace App\Services\Recruiter;

use App\Enums\AcademicLevel;
use App\Enums\ApplicationStage;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Resources\Recruiter\AcademicRecordResource;
use App\Http\Resources\Recruiter\DocumentResource;
use App\Http\Resources\Recruiter\ExperienceResource;
use App\Models\Application;
use App\Models\PlacementDrive;
use App\Models\Recruiter;
use App\Models\Student;
use App\Services\VerifiedAcademicProfileService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * READ-ONLY. Builds the student cards a recruiter sees for the applicants of their own company.
 *
 * Every query starts from Application::visibleToRecruiter(), so a recruiter can never reach another
 * company's applicants, a withdrawn application, or a draft / cancelled drive.
 *
 * What a card contains is deliberately narrower than the college's own view of a student:
 *   - academic records, experiences and documents only once the college verified / approved them
 *   - phone and personal e-mail only from the stages listed in config('tpms.recruiter.contact_visible_stages')
 *   - never date of birth, gender, home city, opt-out reason, review notes or rejection reasons
 *   - the application id is the handle; the student's internal id is not exposed
 */
class ApplicantService
{
    public function __construct(private VerifiedAcademicProfileService $academics)
    {
    }

    // ---- drives -----------------------------------------------------------------------------------

    /** The company's drives that have applicants to look at, with a head-count per stage. */
    public function drives(Recruiter $recruiter): array
    {
        $drives = $this->visibleDrives($recruiter)->orderByDesc('published_at')->orderByDesc('id')->get();
        $stages = $this->stageCounts($recruiter, $drives->pluck('id')->all());

        return $drives->map(fn (PlacementDrive $d) => $this->driveRow($d, $stages[$d->id] ?? []))->all();
    }

    public function drive(Recruiter $recruiter, int $id): array
    {
        $drive = $this->visibleDrives($recruiter)->with('branches:id,code,name')->findOrFail($id);
        $stages = $this->stageCounts($recruiter, [$drive->id]);

        return $this->driveRow($drive, $stages[$drive->id] ?? []) + [
            'description' => $drive->description,
            'additional_requirements' => $drive->additional_requirements,
            'branches' => $drive->branches->map(fn ($b) => ['id' => $b->id, 'code' => $b->code, 'name' => $b->name])->values(),
        ];
    }

    // ---- applicant cards ----------------------------------------------------------------------------

    public function index(Request $request, Recruiter $recruiter): LengthAwarePaginator
    {
        $request->validate([
            'drive_id' => ['sometimes', 'integer'],
            'stage' => ['sometimes', Rule::enum(ApplicationStage::class)->except([ApplicationStage::WITHDRAWN])],
            'branch_id' => ['sometimes', 'integer'],
            'department_id' => ['sometimes', 'integer'],
            'graduation_year' => ['sometimes', 'integer', 'between:2000,2100'],
            'skill' => ['sometimes', 'string', 'max:50'],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        return Application::query()->visibleToRecruiter($recruiter)
            ->with($this->cardRelations())
            ->when($request->filled('drive_id'), fn ($q) => $q->where('drive_id', $request->integer('drive_id')))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->string('stage')->toString()))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('graduation_year'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('graduation_year', $request->integer('graduation_year'))))
            ->when($request->filled('skill'), fn ($q) => $q->whereHas('student', fn ($s) => $s->whereRaw(
                'EXISTS (SELECT 1 FROM jsonb_array_elements_text(students.skills) AS skill WHERE lower(skill) = lower(?))',
                [trim($request->string('skill')->toString())]
            )))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . addcslashes($request->string('search')->toString(), '%_\\') . '%';
                $q->whereHas('student', fn ($s) => $s->where('full_name', 'ilike', $term)->orWhere('university_id', 'ilike', $term));
            })
            ->orderByDesc('applied_at')->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->through(fn (Application $a) => $this->card($a));
    }

    /** The application behind one full card, loaded with everything detail() needs. 404 outside the recruiter's scope. */
    public function find(Recruiter $recruiter, int $applicationId): Application
    {
        return Application::query()->visibleToRecruiter($recruiter)
            ->with($this->cardRelations(detailed: true))
            ->findOrFail($applicationId);
    }

    /** One applicant with everything the college has verified: academics + marksheets, experience + certificates. */
    public function detail(Application $application): array
    {
        $student = $application->student;
        $levelOrder = [AcademicLevel::TENTH->value => 1, AcademicLevel::TWELFTH->value => 2, AcademicLevel::DIPLOMA->value => 3, AcademicLevel::DEGREE_SEM->value => 4];
        $records = $student->academicRecords
            ->sortBy(fn ($r) => $levelOrder[$r->level->value] * 100 + (int) $r->semester)
            ->values();
        $experiences = $student->experiences->sortByDesc('start_date')->values();
        $otherCertificates = $student->documents
            ->filter(fn ($d) => $d->document_type === DocumentType::CERT_OTHER);

        return $this->card($application) + [
            'academic_records' => AcademicRecordResource::collection($records)->resolve(),
            'experiences' => ExperienceResource::collection($experiences)->resolve(),
            'other_certificates' => DocumentResource::collection($otherCertificates->values())->resolve(),
        ];
    }

    // ---- building blocks ------------------------------------------------------------------------------

    /**
     * Relations a card needs. Records and documents are narrowed in SQL to verified / approved ones, so
     * nothing unverified is even loaded. The detailed view adds the proofs and the experience list.
     */
    private function cardRelations(bool $detailed = false): array
    {
        $approvedDocuments = fn ($q) => $q
            ->where('status', DocumentStatus::APPROVED->value)
            ->whereIn('document_type', [DocumentType::RESUME->value, DocumentType::PHOTO->value, DocumentType::CERT_OTHER->value])
            ->orderByDesc('id');

        $relations = [
            'drive:id,title,company_id',
            'student.branch:id,code,name',
            'student.department:id,code,name',
            'student.academicRecords' => fn ($q) => $detailed ? $q->verified()->with('document') : $q->verified(),
            'student.documents' => $approvedDocuments,
        ];

        if ($detailed) {
            $relations['student.experiences'] = fn ($q) => $q->verified()->with('certificate');
        }

        return $relations;
    }

    private function card(Application $a): array
    {
        $student = $a->student;
        $contactVisible = in_array($a->stage->value, config('tpms.recruiter.contact_visible_stages'), true);
        $photo = $student->documents->first(fn ($d) => $d->document_type === DocumentType::PHOTO);
        $resume = $student->documents->first(fn ($d) => $d->document_type === DocumentType::RESUME && $d->is_primary);
        $snapshot = $this->academics->fromRecords($student, $student->academicRecords)->toArray();

        return [
            'application' => [
                'id' => $a->id,
                'stage' => $a->stage->value,
                'applied_at' => $a->applied_at,
                'stage_updated_at' => $a->stage_updated_at,
                'drive' => ['id' => $a->drive->id, 'title' => $a->drive->title],
            ],
            'student' => [
                'university_id' => $student->university_id,
                'full_name' => $student->full_name,
                'department' => $student->department ? ['code' => $student->department->code, 'name' => $student->department->name] : null,
                'branch' => $student->branch ? ['code' => $student->branch->code, 'name' => $student->branch->name] : null,
                'admission_type' => $student->admission_type->value,
                'current_semester' => $student->current_semester,
                'graduation_year' => $student->graduation_year,
                'photo' => $photo ? (new DocumentResource($photo))->resolve() : null,
                'skills' => $student->skills ?? [],
                'languages' => $student->languages ?? [],
                'preferred_roles' => $student->preferred_roles ?? [],
                'preferred_locations' => $student->preferred_locations ?? [],
                'links' => [
                    'github' => $student->github_url,
                    'linkedin' => $student->linkedin_url,
                    'portfolio' => $student->portfolio_url,
                ],
                'contact' => [
                    'visible' => $contactVisible,
                    'email' => $contactVisible ? $student->personal_email : null,
                    'phone' => $contactVisible ? $student->phone : null,
                ],
            ],
            // Verified numbers only; `missing` / `is_complete` are the college's internal bookkeeping.
            'academics' => array_diff_key($snapshot, ['is_complete' => 1, 'missing' => 1]),
            'resume' => $resume ? (new DocumentResource($resume))->resolve() : null,
        ];
    }

    private function visibleDrives(Recruiter $recruiter)
    {
        return PlacementDrive::query()
            ->where('company_id', $recruiter->company_id)
            ->whereIn('status', config('tpms.recruiter.visible_drive_statuses'));
    }

    /** @return array<int, array<string, int>> drive_id => [stage => count], withdrawn applications left out */
    private function stageCounts(Recruiter $recruiter, array $driveIds): array
    {
        if ($driveIds === []) {
            return [];
        }

        $counts = [];
        Application::query()->visibleToRecruiter($recruiter)->whereIn('drive_id', $driveIds)
            ->selectRaw('drive_id, stage, count(*) as total')->groupBy('drive_id', 'stage')->get()
            ->each(function ($row) use (&$counts) {
                $counts[(int) $row->drive_id][$row->stage->value] = (int) $row->total;
            });

        return $counts;
    }

    private function driveRow(PlacementDrive $d, array $stages): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'status' => $d->status->value,
            'employment_type' => $d->employment_type,
            'location' => $d->location,
            'ctc_lpa' => $d->ctc_lpa,
            'ctc_max_lpa' => $d->ctc_max_lpa,
            'graduation_year' => $d->graduation_year,
            'drive_date' => $d->drive_date?->toDateString(),
            'application_deadline' => $d->application_deadline,
            'applicants_count' => array_sum($stages),
            'stages' => $stages,
        ];
    }
}
