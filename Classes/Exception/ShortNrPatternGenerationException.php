<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Exception;

use Throwable;

final class ShortNrPatternGenerationException extends ShortNrPatternException
{
    public function __construct(
        string                 $message,
        public readonly array  $values,
        public readonly string $groupName = '',
        ?Throwable             $previous = null
    ) {
        parent::__construct("Pattern generation error: $message", 0, $previous);
    }
}
