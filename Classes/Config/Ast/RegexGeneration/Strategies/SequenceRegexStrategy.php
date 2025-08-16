<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\RegexGeneration\Strategies;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NestedNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\SequenceNode;
use CPSIT\ShortNr\Config\Ast\RegexGeneration\RegexGenerationStrategyInterface;

final class SequenceRegexStrategy implements RegexGenerationStrategyInterface
{
    public function supports(AstNodeInterface $node): bool
    {
        return $node instanceof SequenceNode && !($node instanceof \CPSIT\ShortNr\Config\Ast\Nodes\SubSequenceNode);
    }

    public function generateRegex(AstNodeInterface $node): string
    {
        /** @var NestedNodeInterface $node */
        $regex = '';
        foreach ($node->getChildren() as $child) {
            $regex .= $child->toRegex();
        }
        
        return $regex;
    }
}