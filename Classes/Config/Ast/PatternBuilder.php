<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast;

use CPSIT\ShortNr\Config\Ast\Compiler\CompiledPatternFactory;
use CPSIT\ShortNr\Config\Ast\Heuristic\HeuristicCompiler;
use CPSIT\ShortNr\Config\Ast\Pattern\PatternCompiler;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Config\Ast\Validation\ValidationPipelineFactory;

final class PatternBuilder
{
    private ?PatternCompiler $patternCompiler = null;
    private ?HeuristicCompiler $heuristicCompiler = null;

    public function __construct(
        private readonly TypeRegistry $typeRegistry
    ) {}

    public function getPatternCompiler(): PatternCompiler
    {
        return $this->patternCompiler ??= new PatternCompiler(
            $this->typeRegistry,
            new CompiledPatternFactory($this->typeRegistry),
            (new ValidationPipelineFactory())->create()
        );
    }

    public function getHeuristicCompiler(): HeuristicCompiler
    {
        return $this->heuristicCompiler ??= new HeuristicCompiler();
    }
}
