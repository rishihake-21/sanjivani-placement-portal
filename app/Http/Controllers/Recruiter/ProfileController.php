<?php

namespace App\Http\Controllers\Recruiter;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in recruiter and the one company they act for. */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $recruiter = $request->attributes->get('recruiter');
        $company = $recruiter->company;

        return response()->json(['data' => [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'designation' => $recruiter->designation,
            'phone' => $recruiter->phone,
            'company' => ['id' => $company->id, 'name' => $company->name, 'website' => $company->website, 'industry' => $company->industry],
        ]]);
    }
}
