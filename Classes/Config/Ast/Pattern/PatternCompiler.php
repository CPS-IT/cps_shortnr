<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Pattern;

use CPSIT\ShortNr\Config\Ast\Compiler\CompiledPattern;
use CPSIT\ShortNr\Config\Ast\Compiler\CompiledPatternFactory;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;

final class PatternCompiler
{private CompiledPatternFactory $factory;

    public function __construct(
        private readonly TypeRegistry $typeRegistry
    ) {
        $this->factory = new CompiledPatternFactory($typeRegistry);
    }

    /**
     * Compile a pattern with caching support
     */
    public function compile(string $pattern): CompiledPattern
    {
        // Parse and compile
        $astRootNode = (new PatternParser($this->typeRegistry, $pattern))->parse();
        
        // Validate tree context after all parent-child relationships are established
        $astRootNode->validateEntireTree();
        
        // Create compiled pattern
        return $this->factory->create($pattern, $astRootNode);
    }

    /**
     * @param CompiledPattern $compiledPattern
     * @return array
     */
    public function dehydrate(CompiledPattern $compiledPattern): array
    {
        return $this->factory->dehydrate($compiledPattern);
    }

    /**
     * @param array $compiledPatternData
     * @return CompiledPattern
     */
    public function hydrate(array $compiledPatternData): CompiledPattern
    {
        return $this->factory->hydrate($compiledPatternData);
    }
}
