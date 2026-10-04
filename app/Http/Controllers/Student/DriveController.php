<?php

namespace App\Http\Controllers\Student;

use App\Enums\DriveStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Services\ApplicationService;
use App\Services\EligibilityEngine;
use App\Services\ProfileCompletenessService;
use App\Support\DrivePresenter;
use App\Support\EligibilityResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a student sees of placement drives.
 * Default list = drives they are ELIGIBLE for; ?scope=all also shows the ones they are not (with the reasons).
 */
class DriveController extends Controller
{
    public function __construct(private EligibilityEngine $engine)
    {
    }

    public function index(Request $request, ProfileCompletenessService $completeness): JsonResponse
    {
        $request->validate(['scope' => ['sometimes', 'in:eligible,all']]);
        $student = $this->currentStudent($request);
        $scope = $request->string('scope', 'eligible')->toString();

        $drives = $this->targetedDrives($student)
            ->where('status', DriveStatus::PUBLISHED->value)->where('application_deadline', '>', now())
            ->with(['company:id,name', 'eligibility', 'branches'])->orderBy('application_deadline')->get();

        $applied = Application::query()->where('student_id', $student->id)->pluck('stage', 'drive_id');
        $profileReady = $drives->isNotEmpty() ? $completeness->summarize($student)['can_apply'] : false;

        $items = $drives->map(function (PlacementDrive $drive) use ($student, $applied, $profileReady) {
            $result = $this->engine->evaluate($student, $drive);
            $mine = $applied[$drive->id] ?? null;

            return DrivePresenter::summary($drive) + [
                'eligibility' => ['status' => $result->status, 'reasons' => $result->reasons()],
                'my_application_stage' => $mine?->value,
                'can_apply' => $mine === null && $result->isEligible() && $profileReady,
            ];
        });

        if ($scope === 'eligible') {
            $items = $items->filter(fn ($i) => $i['eligibility']['status'] === EligibilityResult::ELIGIBLE || $i['my_application_stage'] !== null);
        }

        return response()->json(['data' => $items->values()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $student = $this->currentStudent($request);

        $drive = PlacementDrive::query()->whereIn('status', [DriveStatus::PUBLISHED->value, DriveStatus::CLOSED->value])
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $t) => $this->scopeTargeted($t, $student))
                ->orWhereHas('applications', fn (Builder $a) => $a->where('student_id', $student->id)))
            ->with(['company', 'eligibility', 'branches'])
            ->findOrFail($id);

        $result = $this->engine->evaluate($student, $drive);
        $mine = Application::query()->where('drive_id', $drive->id)->where('student_id', $student->id)->first();

        return response()->json(['data' => DrivePresenter::detail($drive) + [
            'eligibility' => $result->toArray(),   // full per-criterion breakdown
            'my_application' => $mine ? ['id' => $mine->id, 'stage' => $mine->stage->value, 'applied_at' => $mine->applied_at] : null,
        ]]);
    }

    public function apply(Request $request, int $id, ApplicationService $applications): JsonResponse
    {
        $student = $this->currentStudent($request);
        $drive = PlacementDrive::query()->findOrFail($id);

        $application = $applications->apply($student, $drive, $request->user());

        return response()->json(['data' => [
            'id' => $application->id,
            'drive' => ['id' => $drive->id, 'title' => $drive->title, 'company' => $application->drive->company->name],
            'stage' => $application->stage->value,
            'applied_at' => $application->applied_at,
        ]], 201);
    }

    private function targetedDrives(Student $student): Builder
    {
        return PlacementDrive::query()->where(fn (Builder $q) => $this->scopeTargeted($q, $student));
    }

    /** Same graduation year and a target branch that includes the student's branch. */
    private function scopeTargeted(Builder $query, Student $student): Builder
    {
        return $query->where('graduation_year', $student->graduation_year)
            ->whereHas('branches', fn (Builder $b) => $b->where('branches.id', $student->branch_id));
    }
}
