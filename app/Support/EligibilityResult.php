<?php

namespace App\Support;

/**
 * Outcome of checking one student against one drive.
 *   ELIGIBLE              every check passed on VERIFIED data
 *   VERIFICATION_PENDING  nothing failed, but at least one needed value is not verified yet
 *   NOT_ELIGIBLE          at least one check definitively failed
 */
final class EligibilityResult
{
    public const ELIGIBLE = 'ELIGIBLE';
    public const PENDING = 'VERIFICATION_PENDING';
    public const NOT_ELIGIBLE = 'NOT_ELIGIBLE';

    /** @param list<array{criterion:string,label:string,required:mixed,actual:mixed,result:string,message:?string}> $checks */
    public function __construct(
        public readonly string $status,
        public readonly array $checks,
    ) {
    }

    public function isEligible(): bool
    {
        return $this->status === self::ELIGIBLE;
    }

    /** Human-readable reasons for everything that did not pass. @return list<string> */
    public function reasons(): array
    {
        return array_values(array_map(
            fn ($c) => $c['message'],
            array_filter($this->checks, fn ($c) => in_array($c['result'], ['FAIL', 'PENDING'], true))
        ));
    }

    /** @return list<string> criterion keys that failed */
    public function failedCriteria(): array
    {
        return array_values(array_map(
            fn ($c) => $c['criterion'],
            array_filter($this->checks, fn ($c) => $c['result'] === 'FAIL')
        ));
    }

    public function toArray(): array
    {
        return ['status' => $this->status, 'reasons' => $this->reasons(), 'checks' => $this->checks];
    }
}
