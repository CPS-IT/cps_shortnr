<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic;

interface HeuristicPatternInterface
{
    /**
     * @param string $string
     * @return bool
     */
    public function support(string $string): bool;
}
