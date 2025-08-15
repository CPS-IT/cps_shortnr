<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Exception;

final class ShortNrPatternCompilationException extends ShortNrPatternException 
{
    public function __construct(
        string $message,
        public readonly string $pattern,
        public readonly string $generatedRegex,
        ?\Throwable $previous = null
    ) {
        parent::__construct("Pattern compilation error: $message", 0, $previous);
    }
}