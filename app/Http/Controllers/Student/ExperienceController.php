<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProofCertificateRequest;
use App\Http\Requests\StoreExperienceRequest;
use App\Http\Requests\UpdateExperienceRequest;
use App\Http\Resources\ExperienceResource;
use App\Models\Experience;
use App\Services\ExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExperienceController extends Controller
{
    public function __construct(private ExperienceService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $experiences = $this->currentStudent($request)->experiences()->live()
            ->with('certificate')->orderByDesc('start_date')->get();

        return response()->json(['data' => ExperienceResource::collection($experiences)]);
    }

    public function store(StoreExperienceRequest $request): JsonResponse
    {
        $experience = $this->service->create(
            $this->currentStudent($request), $request->user(), $request->validated(), $request->file('certificate')
        );

        return (new ExperienceResource($experience))->response()->setStatusCode(201);
    }

    public function update(UpdateExperienceRequest $request, int $id): ExperienceResource
    {
        $experience = $this->own($request, $id);
        Gate::authorize('modify', $experience);

        return new ExperienceResource($this->service->update($experience, $request->user(), $request->validated()));
    }

    /** Attaching a certificate also submits the entry for verification. */
    public function attachCertificate(ProofCertificateRequest $request, int $id): ExperienceResource
    {
        $experience = $this->own($request, $id);
        Gate::authorize('modify', $experience);

        return new ExperienceResource(
            $this->service->attachCertificate($experience, $request->user(), $request->file('certificate'))
        );
    }

    /** Re-submit after a "data mismatch" rejection (the certificate itself was fine). */
    public function submit(Request $request, int $id): ExperienceResource
    {
        $experience = $this->own($request, $id);
        Gate::authorize('modify', $experience);

        return new ExperienceResource($this->service->submit($experience, $request->user()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $experience = $this->own($request, $id);
        Gate::authorize('modify', $experience);

        $this->service->delete($experience, $request->user());

        return response()->json(null, 204);
    }

    private function own(Request $request, int $id): Experience
    {
        return $this->currentStudent($request)->experiences()->findOrFail($id);
    }
}
