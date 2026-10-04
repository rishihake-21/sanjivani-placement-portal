<?php

namespace App\Http\Controllers\Web;

use App\Enums\RejectionReason;
use App\Http\Controllers\Concerns\PresentsStudentDetail;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Read side of the student portal. Every value comes from the module's own resources and services,
 * so the pages show exactly what GET /api/student/* would return (same shapes, same rules).
 */
class StudentPageController extends Controller
{
    use PresentsStudentDetail;

    public function dashboard(Request $request): View
    {
        $student = $this->currentStudent($request);

        $documents = $this->loadDocuments($student, history: false);
        // The primary resume is listed first so it is never pushed out by the five-row limit.
        $documents = array_merge(
            array_filter($documents, fn ($d) => $d['is_primary']),
            array_filter($documents, fn ($d) => ! $d['is_primary']),
        );

        return view('student.dashboard', $this->context($request, 'student.dashboard', [
            'documents' => array_slice(array_values($documents), 0, 5),
            'notifications' => array_slice($this->loadNotifications($request), 0, 5),
        ]));
    }

    public function profile(Request $request): View
    {
        return view('student.profile', $this->context($request, 'student.profile'));
    }

    public function academic(Request $request): View
    {
        return view('student.academic', $this->context($request, 'student.academic'));
    }

    public function experience(Request $request): View
    {
        return view('student.experience', $this->context($request, 'student.experience'));
    }

    public function documents(Request $request): View
    {
        $history = $request->boolean('history');

        return view('student.documents', $this->context($request, 'student.documents', [
            'documents' => $this->loadDocuments($this->currentStudent($request), $history),
            'history' => $history,
        ]));
    }

    public function notifications(Request $request): View
    {
        $unreadOnly = $request->boolean('unread');
        $all = $this->loadNotifications($request);

        return view('student.notifications', $this->context($request, 'student.notifications', [
            'notifications' => $unreadOnly ? array_values(array_filter($all, fn ($n) => ! $n['read'])) : $all,
            'hasNotifications' => $all !== [],
            'unreadOnly' => $unreadOnly,
        ]));
    }

    // ------------------------------------------------------------------------------------------

    /** Everything the layout and the pages share. */
    private function context(Request $request, string $active, array $extra = []): array
    {
        $student = $this->currentStudent($request);
        Gate::authorize('view', $student);

        $detail = $this->studentDetail($student);
        $profile = $this->present($detail['student'], $request);

        return [
            'active' => $active,
            'user' => $request->user(),
            'student' => Arr::except($profile, ['academic_records', 'experiences', 'documents']),
            'records' => $profile['academic_records'] ?? [],
            'experiences' => $profile['experiences'] ?? [],
            'completeness' => $detail['completeness'],
            'verified' => $detail['verified_academics'],
            'unread' => $request->user()->unreadNotifications()->count(),
            'documents' => [],
            'notifications' => [],
        ] + $extra;
    }

    /** Same query as Student\DocumentController::index. */
    private function loadDocuments(Student $student, bool $history): array
    {
        $query = $student->documents()->orderByDesc('created_at');
        if (! $history) {
            $query->where('status', '<>', 'SUPERSEDED');
        }

        return $this->present(DocumentResource::collection($query->get()), request());
    }

    /** Same payload as Student\NotificationController::index, plus the readable rejection label. */
    private function loadNotifications(Request $request): array
    {
        return $request->user()->notifications()->latest()->limit(50)->get()
            ->map(function ($n) {
                $row = ['id' => $n->id, 'read' => $n->read_at !== null, 'created_at' => $n->created_at] + $n->data;
                $row['reason_label'] = ! empty($row['reason_code']) ? RejectionReason::tryFrom($row['reason_code'])?->label() : null;

                return $row;
            })->all();
    }

    /** A resource as the plain array the API would send (dates as ISO strings, enums as values). */
    private function present(JsonResource $resource, Request $request): array
    {
        $payload = json_decode($resource->toResponse($request)->getContent(), true);

        return $payload['data'] ?? $payload;
    }
}
