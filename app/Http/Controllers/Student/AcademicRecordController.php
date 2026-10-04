<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProofFileRequest;
use App\Http\Requests\StoreAcademicRecordRequest;
use App\Http\Requests\UpdateAcademicRecordRequest;
use App\Http\Resources\AcademicRecordResource;
use App\Models\AcademicRecord;
use App\Services\AcademicRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AcademicRecordController extends Controller
{
    public function __construct(private AcademicRecordService $service)
    {
    }
    public function page(Request $request)
{
    $student = $this->currentStudent($request);

    $records = $student->academicRecords()
        ->live()
        ->with('document')
        ->orderByRaw(
            "array_position(
                ARRAY['TENTH','TWELFTH','DIPLOMA','DEGREE_SEM']::text[],
                level::text
            )"
        )
        ->orderBy('semester')
        ->orderByDesc('version')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | Required academic records
    |--------------------------------------------------------------------------
    */

    $requiredKeys = $student->requiredAcademicKeys();

    $requiredRecords = collect($requiredKeys)
        ->map(fn ($key) => $key[0]->label($key[1]))
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Verified academic values
    |--------------------------------------------------------------------------
    */

    $verifiedRecords = $records->where(
        'status',
        \App\Enums\RecordStatus::VERIFIED
    );

    $tenth = $verifiedRecords->firstWhere('level', 'TENTH');

    $twelfth = $verifiedRecords->firstWhere('level', 'TWELFTH');

    $diploma = $verifiedRecords->firstWhere('level', 'DIPLOMA');

    $degreeRecords = $verifiedRecords
        ->where('level', 'DEGREE_SEM');

    /*
    |--------------------------------------------------------------------------
    | Latest verified CGPA
    |--------------------------------------------------------------------------
    */

    $latestDegree = $degreeRecords
        ->sortByDesc('semester')
        ->first();

    $activeBacklogs = $degreeRecords
        ->sum(fn ($record) => (int) ($record->active_backlogs_after_term ?? 0));

    $totalBacklogs = $degreeRecords
        ->sum(fn ($record) => (int) ($record->backlogs_in_term ?? 0));

    /*
    |--------------------------------------------------------------------------
    | Determine missing required records
    |--------------------------------------------------------------------------
    */

    $missing = collect();

    foreach ($requiredKeys as $key) {
        [$level, $semester] = $key;

        $exists = $verifiedRecords->contains(function ($record) use ($level, $semester) {
            if ($record->level !== $level->value) {
                return false;
            }

            if ($level->value === 'DEGREE_SEM') {
                return (int) $record->semester === (int) $semester;
            }

            return true;
        });

        if (!$exists) {
            $missing->push($level->label($semester));
        }
    }

    $verified = (object) [
        'tenth_percentage' => $tenth?->percentage,
        'twelfth_percentage' => $twelfth?->percentage,
        'diploma_percentage' => $diploma?->percentage,
        'cgpa' => $latestDegree?->cgpa,
        'active_backlogs' => $activeBacklogs,
        'total_backlogs' => $totalBacklogs,
        'is_complete' => $missing->isEmpty(),
        'missing' => $missing,
    ];

    /*
    |--------------------------------------------------------------------------
    | Academic table rows
    |--------------------------------------------------------------------------
    */

    $academicRows = $records->map(function ($record) use ($requiredKeys) {

        $level = $record->level instanceof \App\Enums\AcademicLevel
            ? $record->level
            : \App\Enums\AcademicLevel::from($record->level);

        $required = collect($requiredKeys)->contains(function ($key) use ($record) {
            [$requiredLevel, $requiredSemester] = $key;

            if ($record->level !== $requiredLevel->value) {
                return false;
            }

            if ($requiredLevel->value === 'DEGREE_SEM') {
                return (int) $record->semester === (int) $requiredSemester;
            }

            return true;
        });

        /*
        |----------------------------------------------------------------------
        | Display label
        |----------------------------------------------------------------------
        */

        $label = $level->label($record->semester);

        /*
        |----------------------------------------------------------------------
        | Marks
        |----------------------------------------------------------------------
        */

        if (in_array($record->level, ['TENTH', 'TWELFTH', 'DIPLOMA'], true)) {
            $marks = $record->percentage !== null
                ? number_format($record->percentage, 2) . '%'
                : null;
        } else {
            $marks = $record->sgpa !== null
                ? 'SGPA ' . number_format($record->sgpa, 2)
                : ($record->cgpa !== null
                    ? 'CGPA ' . number_format($record->cgpa, 2)
                    : null);
        }

        /*
        |----------------------------------------------------------------------
        | Status
        |----------------------------------------------------------------------
        */

        $status = $record->status;

        $statusValue = $status instanceof \BackedEnum
            ? $status->value
            : (string) $status;

        $statusMap = [
            'VERIFIED' => [
                'label' => 'Verified',
                'badge' => 'ok',
            ],
            'PENDING' => [
                'label' => 'Pending',
                'badge' => 'wait',
            ],
            'REJECTED' => [
                'label' => 'Rejected',
                'badge' => 'no',
            ],
            'DRAFT' => [
                'label' => 'Draft',
                'badge' => 'neutral',
            ],
        ];

        $statusInfo = $statusMap[$statusValue] ?? [
            'label' => ucfirst(strtolower($statusValue)),
            'badge' => 'neutral',
        ];

        /*
        |----------------------------------------------------------------------
        | Document
        |----------------------------------------------------------------------
        */

        $document = null;

        if ($record->document) {
            $document = [
                'uuid' => $record->document->uuid,
                'original_name' => $record->document->original_name,
            ];
        }

        /*
        |----------------------------------------------------------------------
        | Rejection
        |----------------------------------------------------------------------
        */

        $rejection = null;

        if ($statusValue === 'REJECTED') {
            $rejection = [
                'label' => 'Rejected',
                'text' => $record->rejection_reason,
            ];
        }

        return [
            'id' => $record->id,
            'label' => $label,
            'required' => $required,
            'sub_info' => $record->institution_name,
            'status_label' => $statusInfo['label'],
            'badge_class' => $statusInfo['badge'],
            'revision' => (int) ($record->version ?? 1) > 1,
            'revision_badge' => 'neutral',
            'revision_label' => 'v' . ($record->version ?? 1),
            'rejection' => $rejection,
            'marks' => $marks,
            'document' => $document,
            'document_badge' => $record->document?->status === 'APPROVED'
                ? 'ok'
                : 'wait',
            'document_status' => $record->document
                ? ucfirst(strtolower($record->document->status->value ?? $record->document->status))
                : null,
            'submitted_at' => $record->submitted_at,
            'action' => [
                'id' => $record->id,
                'label' => $statusValue === 'REJECTED'
                    ? 'Revise'
                    : 'View',
            ],
        ];
    })->values();

    return view('student.academic', [
        'student' => $student,
        'requiredRecords' => $requiredRecords,
        'academicRows' => $academicRows,
        'verified' => $verified,
    ]);
}

    public function index(Request $request): JsonResponse
    {
        $student = $this->currentStudent($request);

        $records = $student->academicRecords()->live()->with('document')
            ->orderByRaw("array_position(ARRAY['TENTH','TWELFTH','DIPLOMA','DEGREE_SEM']::text[], level::text)")
            ->orderBy('semester')->orderByDesc('version')
            ->get();

        return response()->json([
            'data' => AcademicRecordResource::collection($records),
            // what this student has to get verified before applying anywhere
            'required' => collect($student->requiredAcademicKeys())->map(fn ($k) => $k[0]->label($k[1]))->values(),
        ]);
    }

    public function store(StoreAcademicRecordRequest $request): JsonResponse
    {
        $student = $this->currentStudent($request);

        $record = $this->service->create($student, $request->user(), $request->validated(), $request->file('document'));

        return (new AcademicRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id): AcademicRecordResource
    {
        $record = $this->own($request, $id);
        Gate::authorize('view', $record);

        return new AcademicRecordResource($record->load('document'));
    }

    public function update(UpdateAcademicRecordRequest $request, int $id): AcademicRecordResource
    {
        $record = $this->own($request, $id);
        Gate::authorize('modify', $record);

        return new AcademicRecordResource($this->service->update($record, $request->user(), $request->validated()));
    }

    public function attachDocument(ProofFileRequest $request, int $id): AcademicRecordResource
    {
        $record = $this->own($request, $id);
        Gate::authorize('modify', $record);

        return new AcademicRecordResource($this->service->attachDocument($record, $request->user(), $request->file('document')));
    }

    public function submit(Request $request, int $id): AcademicRecordResource
    {
        $record = $this->own($request, $id);
        Gate::authorize('modify', $record);

        return new AcademicRecordResource($this->service->submit($record, $request->user()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $record = $this->own($request, $id);
        Gate::authorize('modify', $record);

        $this->service->delete($record, $request->user());

        return response()->json(null, 204);
    }

    /** Looked up through the student's own relation, so another student's id is a plain 404. */
    private function own(Request $request, int $id): AcademicRecord
    {
        return $this->currentStudent($request)->academicRecords()->findOrFail($id);
    }
}
