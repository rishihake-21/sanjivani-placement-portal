<?php

namespace App\Support;

use App\Enums\ApplicationStage as S;

/**
 * Allowed application-stage moves for the TPO.
 * Rounds may be skipped (a drive might have no aptitude test), but never go backwards without `force`.
 * OFFER_RECEIVED and PLACED are NOT set here: they happen only through the placement endpoints,
 * so an application can never claim an offer/placement that has no placement record.
 * WITHDRAWN belongs to the student.
 */
final class StageTransitions
{
    /** @return list<S> */
    public static function allowedNext(S $from): array
    {
        return match ($from) {
            S::APPLIED => [S::SHORTLISTED, S::REJECTED],
            S::SHORTLISTED => [S::APTITUDE, S::TECHNICAL, S::HR, S::SELECTED, S::REJECTED],
            S::APTITUDE => [S::TECHNICAL, S::HR, S::SELECTED, S::REJECTED],
            S::TECHNICAL => [S::HR, S::SELECTED, S::REJECTED],
            S::HR => [S::SELECTED, S::REJECTED],
            S::SELECTED => [S::REJECTED],             // offer withdrawn before it was recorded
            S::OFFER_RECEIVED, S::PLACED, S::WITHDRAWN, S::REJECTED => [],
        };
    }

    /** Stages the TPO may set through the stage endpoints (even with force). */
    public static function settableByTpo(): array
    {
        return [S::APPLIED, S::SHORTLISTED, S::APTITUDE, S::TECHNICAL, S::HR, S::SELECTED, S::REJECTED];
    }

    /** Stages a correction (`force`) may start from. Offers and placements are corrected via placements. */
    public static function correctableFrom(S $from): bool
    {
        return ! in_array($from, [S::OFFER_RECEIVED, S::PLACED, S::WITHDRAWN], true);
    }

    /** Stages from which a student may still withdraw. */
    public static function withdrawable(S $from): bool
    {
        return in_array($from, [S::APPLIED, S::SHORTLISTED, S::APTITUDE, S::TECHNICAL, S::HR], true);
    }
}
