<?php

namespace App\Support\Matching;

/**
 * Result of evaluating a pair of requests: either compatible, with a score and its explanation,
 * or incompatible, with the first mandatory condition that failed.
 */
final readonly class MatchEvaluation
{
    /**
     * @param  list<array{criterion: string, points: int, max_points: int, detail: string}>  $reasons
     */
    private function __construct(
        public bool $compatible,
        public ?string $failedCondition,
        public int $score,
        public array $reasons,
    ) {}

    public static function incompatible(string $condition): self
    {
        return new self(false, $condition, 0, []);
    }

    /**
     * @param  list<array{criterion: string, points: int, max_points: int, detail: string}>  $reasons
     */
    public static function compatible(array $reasons): self
    {
        return new self(true, null, array_sum(array_column($reasons, 'points')), $reasons);
    }
}
