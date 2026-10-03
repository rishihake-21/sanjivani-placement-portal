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
 * "Monitor eligible students". Works for DRAFT drives too, so the TPO can preview the
 * eligible pool before publishing. Evaluated live against VERIFIED data.
 */
class DriveEligibilityController extends Controller
{
    public function __construct(private EligibilityEngine $engine)
    {
    }

    /** GET /tpo/drives/{id}/eligible-students?status=ELIGIBLE|VERIFICATION_PENDING|NOT_ELIGIBLE&branch_id=&search= */
    public function students(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'in:ELIGIBLE,VERIFICATION_PENDING,NOT_ELIGIBLE'],
            'branch_id' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $drive = PlacementDrive::query()->with(['eligibility', 'branches'])->findOrFail($id);
        $filters = fn (Builder $q) => $q
            ->when($request->filled('branch_id'), fn ($w) => $w->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('search'), function ($w) use ($request) {
                $term = '%' . addcslashes($request->string('search')->toString(), '%_\\') . '%';
                $w->where(fn ($s) => $s->where('full_name', 'ilike', $term)->orWhere('university_id', 'ilike', $term));
            });

        $stages = Application::query()->where('drive_id', $drive->id)->pluck('stage', 'student_id');
        $row = fn (Student $s, EligibilityResult $r) => [
            'id' => $s->id,
            'university_id' => $s->university_id,
            'full_name' => $s->full_name,
            'branch' => $s->branch?->code,
            'current_semester' => $s->current_semester,
            'admission_type' => $s->admission_type->value,
            'eligibility' => ['status' => $r->status, 'reasons' => $r->reasons()],
            'application_stage' => ($stages[$s->id] ?? null)?->value,
        ];

        // No status filter: paginate in the database and evaluate just the page.
        if (! $request->filled('status')) {
            $page = $filters($this->engine->candidateQuery($drive)->with('branch:id,code'))->orderBy('university_id')->paginate(25);
            $results = $this->engine->evaluateMany($page->getCollection(), $drive);

            return response()->json($page->through(fn (Student $s) => $row($s, $results[$s->id])));
        }

        // Status filter: evaluate every candidate in chunks, then paginate the matches.
        $wanted = $request->string('status')->toString();
        $matches = collect();
        $filters($this->engine->candidateQuery($drive)->with('branch:id,code'))->chunkById(200, function ($chunk) use ($drive, $wanted, $matches, $row) {
            $results = $this->engine->evaluateMany($chunk, $drive);
            foreach ($chunk as $student) {
                if ($results[$student->id]->status === $wanted) {
                    $matches->push($row($student, $results[$student->id]));
                }
            }
        });

        $sorted = $matches->sortBy('university_id')->values();
        $page = max(1, $request->integer('page', 1));

        return response()->json(new LengthAwarePaginator($sorted->forPage($page, 25)->values(), $sorted->count(), 25, $page, ['path' => $request->url()]));
    }

    /** GET /tpo/drives/{id}/eligibility-summary - counts per outcome and per branch, plus the criteria that exclude most students. */
    public function summary(int $id): JsonResponse
    {
        $drive = PlacementDrive::query()->with(['eligibility', 'branches'])->findOrFail($id);

        $totals = ['candidates' => 0, EligibilityResult::ELIGIBLE => 0, EligibilityResult::PENDING => 0, EligibilityResult::NOT_ELIGIBLE => 0];
        $byBranch = [];
        $failed = [];

        $this->engine->candidateQuery($drive)->with('branch:id,code')->chunkById(200, function ($chunk) use ($drive, &$totals, &$byBranch, &$failed) {
            $results = $this->engine->evaluateMany($chunk, $drive);
            foreach ($chunk as $student) {
                $r = $results[$student->id];
                $code = $student->branch->code;

                $totals['candidates']++;
                $totals[$r->status]++;
                $byBranch[$code] ??= ['candidates' => 0, EligibilityResult::ELIGIBLE => 0, EligibilityResult::PENDING => 0, EligibilityResult::NOT_ELIGIBLE => 0];
                $byBranch[$code]['candidates']++;
                $byBranch[$code][$r->status]++;

                foreach ($r->failedCriteria() as $criterion) {
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
            'by_branch' => $byBranch,
            'failed_criteria' => $failed,
        ]]);
    }
}
