<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Pattern;

use CPSIT\ShortNr\Config\Ast\Compiler\CompiledPattern;
use CPSIT\ShortNr\Config\Ast\Compiler\CompiledPatternFactory;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Config\Ast\Validation\ValidatorInterface;
use CPSIT\ShortNr\Exception\ShortNrPatternCompilationException;
use CPSIT\ShortNr\Exception\ShortNrPatternParseException;
use CPSIT\ShortNr\Exception\ShortNrPatternTypeException;

final class PatternCompiler
{
    public function __construct(
        private readonly TypeRegistry $typeRegistry,
        private readonly CompiledPatternFactory $factory,
        private readonly ValidatorInterface $validator
    ) {}

    /**
     * @param string $pattern
     * @return CompiledPattern
     * @throws ShortNrPatternParseException
     * @throws ShortNrPatternTypeException
     */
    public function compile(string $pattern): CompiledPattern
    {
        // Parse
        $astRootNode = (new PatternParser($this->typeRegistry, $pattern))->parse();

        // Validate using injected validator pipeline
        $this->validator->validate($astRootNode);
        
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
     * @throws ShortNrPatternCompilationException
     */
    public function hydrate(array $compiledPatternData): CompiledPattern
    {
        return $this->factory->hydrate($compiledPatternData);
    }
}
