<?php

namespace App\Http\Controllers\Tpo;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Services\EligibilityEngine;
use App\Support\EligibilityResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Preview and monitor eligibility across all divisions of each department targeted by a drive.
 */
class DriveEligibilityController extends Controller
{
    public function __construct(private EligibilityEngine $engine)
    {
    }

    /** GET /api/tpo/drives/{id}/eligible-students?division_id=&status=&search= */
    public function students(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'in:ELIGIBLE,VERIFICATION_PENDING,NOT_ELIGIBLE'],
            'division_id' => ['sometimes', 'integer'],
            // Legacy clients may still send branch_id.
            'branch_id' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $drive = PlacementDrive::query()->with(['eligibility', 'branches', 'departments'])->findOrFail($id);
        $filters = fn (Builder $query) => $query
            ->when($request->filled('division_id'), fn ($q) => $q->where('division_id', $request->integer('division_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . addcslashes($request->string('search')->toString(), '%_\\') . '%';
                $q->where(fn ($student) => $student
                    ->where('full_name', 'ilike', $term)
                    ->orWhere('university_id', 'ilike', $term));
            });

        $stages = Application::query()->where('drive_id', $drive->id)->pluck('stage', 'student_id');
        $row = fn (Student $student, EligibilityResult $result) => [
            'id' => $student->id,
            'university_id' => $student->university_id,
            'full_name' => $student->full_name,
            'division' => $student->division?->code ?? $student->branch?->code,
            'division_name' => $student->division?->name ?? $student->branch?->name,
            // Compatibility keys for consumers not yet upgraded to division terminology.
            'branch' => $student->division?->code ?? $student->branch?->code,
            'current_semester' => $student->current_semester,
            'admission_type' => $student->admission_type->value,
            'eligibility' => ['status' => $result->status, 'reasons' => $result->reasons()],
            'application_stage' => ($stages[$student->id] ?? null)?->value,
        ];

        if (! $request->filled('status')) {
            $page = $filters($this->engine->candidateQuery($drive)->with(['division:id,code,name', 'branch:id,code,name']))
                ->orderBy('university_id')->paginate(25);
            $results = $this->engine->evaluateMany($page->getCollection(), $drive);

            return response()->json($page->through(fn (Student $student) => $row($student, $results[$student->id])));
        }

        $wanted = $request->string('status')->toString();
        $matches = collect();
        $filters($this->engine->candidateQuery($drive)->with(['division:id,code,name', 'branch:id,code,name']))
            ->chunkById(200, function ($chunk) use ($drive, $wanted, $matches, $row) {
                $results = $this->engine->evaluateMany($chunk, $drive);
                foreach ($chunk as $student) {
                    if ($results[$student->id]->status === $wanted) {
                        $matches->push($row($student, $results[$student->id]));
                    }
                }
            });

        $sorted = $matches->sortBy('university_id')->values();
        $page = max(1, $request->integer('page', 1));

        return response()->json(new LengthAwarePaginator(
            $sorted->forPage($page, 25)->values(),
            $sorted->count(),
            25,
            $page,
            ['path' => $request->url()]
        ));
    }

    /** Counts outcomes, plus a breakdown by division and the criteria that exclude most candidates. */
    public function summary(int $id): JsonResponse
    {
        $drive = PlacementDrive::query()->with(['eligibility', 'branches', 'departments'])->findOrFail($id);
        $totals = [
            'candidates' => 0,
            EligibilityResult::ELIGIBLE => 0,
            EligibilityResult::PENDING => 0,
            EligibilityResult::NOT_ELIGIBLE => 0,
        ];
        $byDivision = [];
        $failed = [];

        $this->engine->candidateQuery($drive)
            ->with(['division:id,code,name', 'branch:id,code,name'])
            ->chunkById(200, function ($chunk) use ($drive, &$totals, &$byDivision, &$failed) {
                $results = $this->engine->evaluateMany($chunk, $drive);
                foreach ($chunk as $student) {
                    $result = $results[$student->id];
                    $divisionCode = $student->division?->code ?? $student->branch?->code ?? 'UNASSIGNED';

                    $totals['candidates']++;
                    $totals[$result->status]++;
                    $byDivision[$divisionCode] ??= [
                        'division' => $divisionCode,
                        'division_name' => $student->division?->name ?? $student->branch?->name ?? 'Unassigned',
                        'candidates' => 0,
                        EligibilityResult::ELIGIBLE => 0,
                        EligibilityResult::PENDING => 0,
                        EligibilityResult::NOT_ELIGIBLE => 0,
                    ];
                    $byDivision[$divisionCode]['candidates']++;
                    $byDivision[$divisionCode][$result->status]++;

                    foreach ($result->failedCriteria() as $criterion) {
                        $failed[$criterion] = ($failed[$criterion] ?? 0) + 1;
                    }
                }
            });

        arsort($failed);

        return response()->json(['data' => [
            'drive_id' => $drive->id,
            'candidates' => $totals['candidates'],
            'eligible' => $totals[EligibilityResult::ELIGIBLE],
            'verification_pending' => $totals[EligibilityResult::PENDING],
            'not_eligible' => $totals[EligibilityResult::NOT_ELIGIBLE],
            'by_division' => array_values($byDivision),
            // Temporary alias for older UI clients.
            'by_branch' => $byDivision,
            'failed_criteria' => $failed,
        ]]);
    }
}
