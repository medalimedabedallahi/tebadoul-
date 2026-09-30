<?php

namespace App\Contracts;

use App\Support\Matching\Candidate;
use App\Support\Matching\MatchEvaluation;

/**
 * A versioned set of matching rules (PRD sections 7.1 and 7.2). The version is stored on every
 * match, so a score can always be explained with the rules that produced it.
 */
interface MatchingRules
{
    public function version(): string;

    /**
     * Mandatory conditions first (any failure makes the pair incompatible), then the score. The
     * evaluation is symmetric: evaluate($a, $b) and evaluate($b, $a) give the same result.
     */
    public function evaluate(Candidate $a, Candidate $b): MatchEvaluation;
}
