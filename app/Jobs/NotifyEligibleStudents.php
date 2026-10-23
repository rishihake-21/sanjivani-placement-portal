<?php

namespace App\Jobs;

use App\Enums\DriveStatus;
use App\Models\PlacementDrive;
use App\Models\User;
use App\Notifications\PlacementNotice;
use App\Services\EligibilityEngine;
use App\Support\EligibilityResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * After publishing: tell students the drive exists.
 * ELIGIBLE students are told they can apply; VERIFICATION_PENDING students are told what to get verified.
 * NOT_ELIGIBLE students are not notified (they can still see the reasons under scope=all).
 */
class NotifyEligibleStudents implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(public int $driveId)
    {
    }

    public function handle(EligibilityEngine $engine): void
    {
        $drive = PlacementDrive::query()->with(['company:id,name', 'eligibility', 'branches'])->find($this->driveId);
        if ($drive === null || $drive->status !== DriveStatus::PUBLISHED) {
            return;
        }

        $engine->candidateQuery($drive)
            ->select(['id', 'user_id', 'graduation_year', 'branch_id', 'admission_type', 'current_semester', 'opted_out_of_placement'])
            ->chunkById(500, function ($students) use ($engine, $drive) {
                $results = $engine->evaluateMany($students, $drive);
                $userIds = $students->pluck('user_id')->all();

                // Fetch already notified user IDs for this chunk in 1 query instead of N queries
                $alreadyNotifiedUserIds = $this->alreadyNotifiedUserIds($userIds, $drive->id);

                $now = now();
                $inserts = [];

                foreach ($students as $student) {
                    $result = $results[$student->id];
                    if ($result->status === EligibilityResult::NOT_ELIGIBLE) {
                        continue;
                    }

                    // Re-runs (a retried job) must not deliver the same drive notice twice.
                    if (isset($alreadyNotifiedUserIds[$student->user_id])) {
                        continue;
                    }

                    $inserts[] = [
                        'id' => (string) Str::uuid(),
                        'type' => PlacementNotice::class,
                        'notifiable_type' => User::class,
                        'notifiable_id' => $student->user_id,
                        'data' => json_encode([
                            'kind' => 'drive_published',
                            'title' => 'New drive: ' . $drive->company->name . ' - ' . $drive->title,
                            'body' => $result->isEligible()
                                ? 'You are eligible. Apply before the deadline.'
                                : 'Complete verification to become eligible: ' . implode(' ', $result->reasons()),
                            'drive_id' => $drive->id,
                            'eligibility' => $result->status,
                        ]),
                        'read_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($inserts !== []) {
                    DB::table('notifications')->insert($inserts);
                }
            });
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, true>
     */
    private function alreadyNotifiedUserIds(array $userIds, int $driveId): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $userIds)
            ->where('type', PlacementNotice::class)
            ->get(['notifiable_id', 'data']);

        $notified = [];
        foreach ($rows as $row) {
            $data = json_decode((string) $row->data, true);
            if (($data['kind'] ?? null) === 'drive_published' && (int) ($data['drive_id'] ?? 0) === $driveId) {
                $notified[(int) $row->notifiable_id] = true;
            }
        }

        return $notified;
    }
}
