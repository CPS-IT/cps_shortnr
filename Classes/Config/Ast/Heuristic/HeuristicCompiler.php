<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic;

final class HeuristicCompiler
{
    /**
     * Compile heuristic from compiled patterns
     */
    public function compile(iterable $compiledPatterns): PatternHeuristic
    {
        return PatternHeuristic::buildFromPatterns($compiledPatterns);
    }

    /**
     * Recreate heuristic from cached data
     */
    public function hydrate(array $data): PatternHeuristic
    {
        return PatternHeuristic::fromArray($data);
    }

    /**
     * Convert heuristic to cacheable array
     */
    public function dehydrate(PatternHeuristic $heuristic): array
    {
        return $heuristic->toArray();
    }
}
