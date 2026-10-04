<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $applications = Application::query()->where('student_id', $this->currentStudent($request)->id)
            ->with(['drive.company:id,name', 'history'])->orderByDesc('applied_at')->get();

        return response()->json(['data' => $applications->map(fn (Application $a) => [
            'id' => $a->id,
            'drive' => ['id' => $a->drive->id, 'title' => $a->drive->title, 'company' => $a->drive->company->name, 'status' => $a->drive->status->value],
            'stage' => $a->stage->value,
            'applied_at' => $a->applied_at,
            'stage_updated_at' => $a->stage_updated_at,
            'history' => $a->history->map(fn ($h) => ['from' => $h->from_stage, 'to' => $h->to_stage, 'remarks' => $h->remarks, 'at' => $h->created_at])->values(),
        ])]);
    }

    public function withdraw(Request $request, int $id, ApplicationService $applications): JsonResponse
    {
        // Looked up through the student's own applications: someone else's id is a plain 404.
        $application = Application::query()->where('student_id', $this->currentStudent($request)->id)->findOrFail($id);
        $withdrawn = $applications->withdraw($application, $request->user());

        return response()->json(['data' => ['id' => $withdrawn->id, 'stage' => $withdrawn->stage->value]]);
    }
}
