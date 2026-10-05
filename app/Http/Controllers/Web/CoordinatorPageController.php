<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\PresentsStudentDetail;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Coordinator\ApplicationController;
use App\Http\Controllers\Coordinator\DriveController;
use App\Http\Controllers\Coordinator\PlacementController;
use App\Http\Controllers\Coordinator\ReuploadRequestController;
use App\Http\Controllers\Coordinator\StudentController;
use App\Http\Controllers\Coordinator\VerificationController;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Coordinator;
use App\Models\Document;
use App\Models\PlacementDrive;
use App\Models\ReuploadRequest;
use App\Models\Student;
use App\Services\DepartmentStatsService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CoordinatorPageController extends Controller
{
    use PresentsStudentDetail;

    public function __construct(
        private DepartmentStatsService $stats,
        private StudentController $students,
        private VerificationController $verification,
        private DriveController $drives,
        private ApplicationController $applications,
        private PlacementController $placements,
        private ReuploadRequestController $reuploads,
    ) {
    }

    public function dashboard(Request $request): View
    {
        $year = $this->year($request);

        return view('coordinator.dashboard', $this->context($request, 'coordinator.dashboard', [
            'stats' => $this->stats->dashboard((int) $request->user()->department_id, $year),
            'graduationYear' => $year,
            'years' => $this->years($request),
        ]));
    }

    public function profile(Request $request): View
    {
        return view('coordinator.profile', $this->context($request, 'coordinator.profile'));
    }

    public function queue(Request $request): View
    {
        $queue = $this->payload($this->verification->queue($this->clean($request)), $request);

        return view('coordinator.queue', $this->context($request, 'coordinator.queue', [
            'queue' => $queue,
        ]));
    }

    public function students(Request $request): View
    {
        [$rows, $pg] = $this->rowsAndPager($this->payload($this->students->index($this->clean($request)), $request));

        return view('coordinator.students', $this->context($request, 'coordinator.students', [
            'rows' => $rows,
            'pg' => $pg,
            'filters' => $request->only(['search', 'branch_id', 'semester', 'admission_type', 'only_pending']),
            'branches' => $this->branches($request),
        ]));
    }

    public function student(Request $request, int $id): View
    {
        $student = Student::query()->where('department_id', (int) $request->user()->department_id)->findOrFail($id);
        Gate::authorize('view', $student);

        $detail = $this->studentDetail($student);
        $profile = $this->present($detail['student'], $request);
        $documents = $profile['documents'] ?? [];
        $documentIds = Document::query()->whereIn('uuid', Arr::pluck($documents, 'uuid'))->pluck('id', 'uuid');

        foreach ($documents as &$document) {
            if (isset($document['uuid'], $documentIds[$document['uuid']])) {
                $document['id'] = $documentIds[$document['uuid']];
            }
        }
        unset($document);

        $progress = $this->payload($this->applications->studentProgress($this->clean($request), $id), $request)['data'];

        return view('coordinator.student-show', $this->context($request, 'coordinator.students', [
            'student' => Arr::except($profile, ['academic_records', 'experiences', 'documents']),
            'records' => $profile['academic_records'] ?? [],
            'experiences' => $profile['experiences'] ?? [],
            'documents' => $documents,
            'completeness' => $detail['completeness'],
            'verified' => $detail['verified_academics'],
            'progress' => $progress,
            'reuploads' => $this->studentReuploads($request, $id),
        ]));
    }

    public function reuploads(Request $request): View
    {
        [$rows, $pg] = $this->rowsAndPager($this->payload($this->reuploads->index($this->clean($request)), $request));

        return view('coordinator.reuploads', $this->context($request, 'coordinator.reuploads', [
            'rows' => $rows,
            'pg' => $pg,
            'status' => $request->query('status', ''),
        ]));
    }

    public function drives(Request $request): View
    {
        [$rows, $pg] = $this->rowsAndPager($this->payload($this->drives->index($this->clean($request)), $request));

        return view('coordinator.drives', $this->context($request, 'coordinator.drives', [
            'rows' => $rows,
            'pg' => $pg,
            'filters' => $request->only(['status', 'graduation_year']),
        ]));
    }

    public function drive(Request $request, int $id): View
    {
        $drive = $this->payload($this->drives->show($this->clean($request), $id), $request)['data'];

        return view('coordinator.drive-show', $this->context($request, 'coordinator.drives', [
            'drive' => $drive,
        ]));
    }

    public function applications(Request $request): View
    {
        [$rows, $pg] = $this->rowsAndPager($this->payload($this->applications->index($this->clean($request)), $request));

        return view('coordinator.applications', $this->context($request, 'coordinator.applications', [
            'rows' => $rows,
            'pg' => $pg,
            'filters' => $request->only(['drive_id', 'stage', 'branch_id', 'search']),
            'drives' => $this->driveOptions($request),
            'branches' => $this->branches($request),
        ]));
    }

    public function application(Request $request, int $id): View
    {
        $application = $this->payload($this->applications->show($this->clean($request), $id), $request)['data'];

        return view('coordinator.application-show', $this->context($request, 'coordinator.applications', [
            'application' => $application,
        ]));
    }

    public function placements(Request $request): View
    {
        [$rows, $pg] = $this->rowsAndPager($this->payload($this->placements->index($this->clean($request)), $request));

        return view('coordinator.placements', $this->context($request, 'coordinator.placements', [
            'rows' => $rows,
            'pg' => $pg,
            'filters' => $request->only(['status', 'branch_id', 'graduation_year', 'company_id']),
            'branches' => $this->branches($request),
            'companies' => $this->companyOptions($request),
        ]));
    }

    public function reports(Request $request): View
    {
        $year = $this->year($request);

        return view('coordinator.reports', $this->context($request, 'coordinator.reports', [
            'rows' => $this->stats->branchSummary((int) $request->user()->department_id, $year),
            'graduationYear' => $year,
        ]));
    }

    private function context(Request $request, string $active, array $extra = []): array
    {
        $profile = $this->profileData($request);

        return [
            'active' => $active,
            'user' => $request->user(),
            'profile' => $profile,
            'department' => $profile['department'],
        ] + $extra;
    }

    private function profileData(Request $request): array
    {
        $coordinator = Coordinator::query()->with(['user:id,name,email', 'department:id,name,code'])
            ->where('user_id', $request->user()->id)->firstOrFail();

        return [
            'id' => $coordinator->id,
            'name' => $coordinator->user->name,
            'email' => $coordinator->user->email,
            'employee_id' => $coordinator->employee_id,
            'phone' => $coordinator->phone,
            'designation' => $coordinator->designation,
            'department' => $coordinator->department->only(['id', 'name', 'code']),
            'active_from' => $coordinator->active_from?->toDateString(),
        ];
    }

    private function payload(mixed $response, Request $request): array
    {
        if ($response instanceof JsonResource || $response instanceof Responsable) {
            $response = $response->toResponse($request);
        }

        if ($response instanceof JsonResponse) {
            return json_decode($response->getContent(), true) ?: [];
        }

        return (array) $response;
    }

    private function present(JsonResource $resource, Request $request): array
    {
        $payload = $this->payload($resource, $request);

        return $payload['data'] ?? $payload;
    }

    private function rowsAndPager(array $payload): array
    {
        $meta = $payload['meta'] ?? $payload;
        $links = $payload['links'] ?? [];

        return [
            $payload['data'] ?? [],
            [
                'current_page' => (int) ($meta['current_page'] ?? 1),
                'last_page' => (int) ($meta['last_page'] ?? 1),
                'total' => (int) ($meta['total'] ?? count($payload['data'] ?? [])),
                'prev_url' => $links['prev'] ?? $payload['prev_page_url'] ?? null,
                'next_url' => $links['next'] ?? $payload['next_page_url'] ?? null,
            ],
        ];
    }

    private function clean(Request $request): Request
    {
        $query = array_filter($request->query(), fn ($value) => $value !== null && $value !== '');
        $clean = $request->duplicate($query);
        $clean->setUserResolver(fn () => $request->user());
        $clean->setRouteResolver(fn () => $request->route());

        return $clean;
    }

    private function year(Request $request): ?int
    {
        $request->validate(['graduation_year' => ['sometimes', 'nullable', 'integer', 'between:2000,2100']]);

        return $request->filled('graduation_year') ? $request->integer('graduation_year') : null;
    }

    private function years(Request $request): array
    {
        $now = (int) date('Y');

        return Student::query()
            ->where('department_id', (int) $request->user()->department_id)
            ->distinct()
            ->pluck('graduation_year')
            ->map(fn ($year) => (int) $year)
            ->merge([$now, $now + 1, $now + 2, $now + 3])
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    private function branches(Request $request): array
    {
        return Branch::query()
            ->where('department_id', (int) $request->user()->department_id)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map->only(['id', 'code', 'name'])
            ->values()
            ->all();
    }

    private function driveOptions(Request $request): array
    {
        return PlacementDrive::query()
            ->visibleToDepartment((int) $request->user()->department_id)
            ->with('company:id,name')
            ->orderByDesc('published_at')
            ->limit(100)
            ->get(['id', 'company_id', 'title'])
            ->map(fn (PlacementDrive $drive) => [
                'id' => $drive->id,
                'company' => $drive->company?->name,
                'title' => $drive->title,
            ])
            ->all();
    }

    private function companyOptions(Request $request): array
    {
        return Company::query()
            ->whereHas('drives.applications.student', fn ($query) => $query->where('department_id', (int) $request->user()->department_id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map->only(['id', 'name'])
            ->values()
            ->all();
    }

    private function studentReuploads(Request $request, int $studentId): array
    {
        return ReuploadRequest::query()
            ->where('student_id', $studentId)
            ->whereHas('student', fn ($query) => $query->where('department_id', (int) $request->user()->department_id))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ReuploadRequest $reupload) => [
                'id' => $reupload->id,
                'subject_type' => $reupload->subject_type->value,
                'subject_id' => $reupload->subject_id,
                'reason' => $reupload->reason,
                'status' => $reupload->status->value,
                'created_at' => $reupload->created_at,
                'closed_at' => $reupload->closed_at,
            ])
            ->all();
    }
}
