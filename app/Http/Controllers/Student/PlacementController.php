<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The student's own official placement records. Read-only: students never set their own outcome. */
class PlacementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $placements = Placement::query()->where('student_id', $this->currentStudent($request)->id)
            ->with('company:id,name')->orderByDesc('offer_date')->get();

        return response()->json(['data' => $placements->map(fn (Placement $p) => [
            'id' => $p->id,
            'company' => $p->company->name,
            'role_title' => $p->role_title,
            'ctc_lpa' => $p->ctc_lpa,
            'location' => $p->location,
            'offer_date' => $p->offer_date?->toDateString(),
            'joining_date' => $p->joining_date?->toDateString(),
            'status' => $p->status->value,
            'is_official' => $p->status->value === 'PLACED',
        ])]);
    }
}
