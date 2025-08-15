<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Exception;

final class ShortNrPatternTypeException extends ShortNrPatternException 
{
    public function __construct(
        string $message,
        public readonly string $typeName,
        public readonly array $availableTypes = [],
        ?\Throwable $previous = null
    ) {
        $available = empty($availableTypes) ? '' : ' Available: ' . implode(', ', $availableTypes);
        parent::__construct("Type error: $message$available", 0, $previous);
    }
}
