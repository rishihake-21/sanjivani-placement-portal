<?php

namespace App\Jobs;

use App\Enums\DriveStatus;
use App\Models\PlacementDrive;
use App\Notifications\PlacementNotice;
use App\Services\EligibilityEngine;
use App\Support\EligibilityResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * After publishing: tell students the drive exists.
 * ELIGIBLE students are told they can apply; VERIFICATION_PENDING students are told what to get verified.
 * NOT_ELIGIBLE students are not notified (they can still see the reasons under scope=all).
 */
class NotifyEligibleStudents implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $driveId)
    {
    }

    public function handle(EligibilityEngine $engine): void
    {
        $drive = PlacementDrive::query()->with(['company:id,name', 'eligibility', 'branches'])->find($this->driveId);
        if ($drive === null || $drive->status !== DriveStatus::PUBLISHED) {
            return;
        }

        $engine->candidateQuery($drive)->with('user')->chunkById(200, function ($students) use ($engine, $drive) {
            $results = $engine->evaluateMany($students, $drive);

            foreach ($students as $student) {
                $result = $results[$student->id];
                if ($result->status === EligibilityResult::NOT_ELIGIBLE) {
                    continue;
                }

                $student->user->notify(new PlacementNotice(
                    'drive_published',
                    'New drive: ' . $drive->company->name . ' - ' . $drive->title,
                    $result->isEligible()
                        ? 'You are eligible. Apply before the deadline.'
                        : 'Complete verification to become eligible: ' . implode(' ', $result->reasons()),
                    ['drive_id' => $drive->id, 'eligibility' => $result->status],
                ));
            }
        });
    }
}
