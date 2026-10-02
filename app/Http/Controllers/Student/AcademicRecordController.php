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
