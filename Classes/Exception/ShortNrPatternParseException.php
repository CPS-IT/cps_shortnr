<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Exception;

use Throwable;

final class ShortNrPatternParseException extends ShortNrPatternException
{
    public function __construct(
        string                 $message,
        public readonly string $pattern,
        int                    $position = 0,
        ?Throwable             $previous = null
    ) {
        parent::__construct("Pattern parse error: $message", $position, $previous);
    }
}
