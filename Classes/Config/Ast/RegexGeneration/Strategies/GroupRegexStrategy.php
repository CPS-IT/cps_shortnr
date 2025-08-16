<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\RegexGeneration\Strategies;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\GroupNode;
use CPSIT\ShortNr\Config\Ast\RegexGeneration\RegexGenerationStrategyInterface;
use CPSIT\ShortNr\Exception\ShortNrPatternException;

final class GroupRegexStrategy implements RegexGenerationStrategyInterface
{
    public function supports(AstNodeInterface $node): bool
    {
        return $node instanceof GroupNode;
    }

    public function generateRegex(AstNodeInterface $node): string
    {
        /** @var GroupNode $node */
        $typeRegistry = $node->getTypeRegistry();
        if ($typeRegistry === null) {
            throw new ShortNrPatternException("TypeRegistry not set on GroupNode");
        }

        $typeObj = $typeRegistry->getType($node->getType());
        if (!$typeObj) {
            throw new \InvalidArgumentException("Could not resolve type: " . $node->getType());
        }

        $pattern = $typeObj->getConstrainedPattern($node->getConstraints());
        return '(?P<' . $node->getGroupId() . '>' . $pattern . ')';
    }
}