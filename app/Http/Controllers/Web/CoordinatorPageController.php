<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\PresentsStudentDetail;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Company;
use App\Models\Coordinator;
use App\Models\Document;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Services\Coordinator\ApplicationDirectoryService;
use App\Services\Coordinator\DriveDirectoryService;
use App\Services\Coordinator\PlacementDirectoryService;
use App\Services\Coordinator\ReuploadRequestDirectoryService;
use App\Services\Coordinator\StudentDirectoryService;
use App\Services\Coordinator\VerificationQueueService;
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
        private StudentDirectoryService $students,
        private VerificationQueueService $verification,
        private DriveDirectoryService $drives,
        private ApplicationDirectoryService $applications,
        private PlacementDirectoryService $placements,
        private ReuploadRequestDirectoryService $reuploads,
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
        [$rows, $pg] = $this->rowsAndPager($this->payload($this->students->directory($this->clean($request)), $request));

        return view('coordinator.students', $this->context($request, 'coordinator.students', [
            'rows' => $rows,
            'pg' => $pg,
            'filters' => $request->only(['search', 'division_id', 'semester', 'admission_type', 'only_pending']),
            'divisions' => $this->divisions($request),
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

        $progress = $this->payload($this->applications->studentProgress($this->clean($request), $id), $request);

        return view('coordinator.student-show', $this->context($request, 'coordinator.students', [
            'student' => Arr::except($profile, ['academic_records', 'experiences', 'documents']),
            'records' => $profile['academic_records'] ?? [],
            'experiences' => $profile['experiences'] ?? [],
            'documents' => $documents,
            'completeness' => $detail['completeness'],
            'verified' => $detail['verified_academics'],
            'progress' => $progress,
            'reuploads' => $this->reuploads->forStudent($this->clean($request), $id),
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
        $drive = $this->payload($this->drives->show($this->clean($request), $id), $request);

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
            'filters' => $request->only(['drive_id', 'stage', 'division_id', 'search']),
            'drives' => $this->driveOptions($request),
            'branches' => $this->branches($request),
        ]));
    }

    public function application(Request $request, int $id): View
    {
        $application = $this->payload($this->applications->show($this->clean($request), $id), $request);

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
            'filters' => $request->only(['status', 'division_id', 'graduation_year', 'company_id']),
            'branches' => $this->branches($request),
            'companies' => $this->companyOptions($request),
        ]));
    }

    public function reports(Request $request): View
    {
        $year = $this->year($request);

        return view('coordinator.reports', $this->context($request, 'coordinator.reports', [
            'rows' => $this->stats->divisionSummary((int) $request->user()->department_id, $year),
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

    /**
     * A read model result as the plain array the JSON API would send (dates as ISO strings, enums as values),
     * so the Blade screens render exactly what GET /api/coordinator/* returns.
     */
    private function payload(mixed $value, Request $request): array
    {
        if ($value instanceof JsonResource || $value instanceof Responsable) {
            $value = $value->toResponse($request);
        }

        if ($value instanceof JsonResponse) {
            return json_decode($value->getContent(), true) ?: [];
        }

        return json_decode(json_encode($value), true) ?: [];
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

    private function divisions(Request $request): array
    {
        return Division::query()
            ->where('department_id', (int) $request->user()->department_id)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'department_id'])
            ->map->only(['id', 'code', 'name', 'department_id'])
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
}
