<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\RegexGeneration;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;

interface RegexGenerationStrategyInterface
{
    public function supports(AstNodeInterface $node): bool;
    public function generateRegex(AstNodeInterface $node): string;
}