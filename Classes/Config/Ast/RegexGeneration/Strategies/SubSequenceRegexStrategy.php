<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\RegexGeneration\Strategies;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\SubSequenceNode;

final class SubSequenceRegexStrategy extends SequenceRegexStrategy
{
    public function supports(AstNodeInterface $node): bool
    {
        return $node instanceof SubSequenceNode;
    }

    protected function wrapSequence(string $regex): string
    {
        return '(?:' . $regex . ')?';
    }
}