<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\RegexGeneration;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use InvalidArgumentException;

final class RegexGenerator
{
    /** @var RegexGenerationStrategyInterface[] */
    private array $strategies = [];

    public function addStrategy(RegexGenerationStrategyInterface $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    public function generate(AstNodeInterface $node): string
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($node)) {
                return $strategy->generateRegex($node);
            }
        }

        throw new InvalidArgumentException(
            'No regex generation strategy found for node type: ' . $node::class
        );
    }
}