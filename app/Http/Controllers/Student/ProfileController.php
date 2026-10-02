<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\PresentsStudentDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    use PresentsStudentDetail;

    public function show(Request $request): JsonResponse
    {
        $student = $this->currentStudent($request);
        Gate::authorize('view', $student);

        return response()->json($this->studentDetail($student));
    }

    public function update(UpdateProfileRequest $request, AuditLogger $audit): JsonResponse
    {
        $student = $this->currentStudent($request);
        Gate::authorize('update', $student);

        $data = $request->validated();
        $confirm = (bool) Arr::pull($data, 'confirm_identity', false);

        $student->fill($data);
        if ($confirm) {
            $student->identity_confirmed_at = now();
        }
        if (array_key_exists('opted_out_of_placement', $data) && ! $data['opted_out_of_placement']) {
            $student->opt_out_reason = null;
        }

        $changed = array_keys($student->getDirty());
        if ($changed !== []) {
            $old = [];
            foreach ($changed as $key) {
                $old[$key] = $student->getOriginal($key);
            }
            $student->save();
            $audit->record('student.profile_updated', $student, $student->id, $old, $student->only($changed), $request->user());
        }

        return response()->json($this->studentDetail($student->refresh()));
    }
}
