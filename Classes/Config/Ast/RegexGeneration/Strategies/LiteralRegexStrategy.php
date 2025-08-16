<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\RegexGeneration\Strategies;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\LiteralNode;
use CPSIT\ShortNr\Config\Ast\RegexGeneration\RegexGenerationStrategyInterface;

final class LiteralRegexStrategy implements RegexGenerationStrategyInterface
{
    public function supports(AstNodeInterface $node): bool
    {
        return $node instanceof LiteralNode;
    }

    public function generateRegex(AstNodeInterface $node): string
    {
        /** @var LiteralNode $node */
        return preg_quote($node->getText(), '/');
    }
}