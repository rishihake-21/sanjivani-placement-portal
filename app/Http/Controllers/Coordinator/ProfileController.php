<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $coordinator = Coordinator::query()->with(['user:id,name,email', 'department:id,name,code'])
            ->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(['data' => [
            'id' => $coordinator->id,
            'name' => $coordinator->user->name,
            'email' => $coordinator->user->email,
            'employee_id' => $coordinator->employee_id,
            'phone' => $coordinator->phone,
            'designation' => $coordinator->designation,
            'department' => $coordinator->department->only(['id', 'name', 'code']),
            'active_from' => $coordinator->active_from?->toDateString(),
        ]]);
    }
}
