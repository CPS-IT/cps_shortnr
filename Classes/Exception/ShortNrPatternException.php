<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Exception;

use Throwable;

class ShortNrPatternException extends ShortNrException
{
    public function __construct(
        string              $message,
        public readonly int $position = 0,
        ?Throwable          $previous = null
    ) {
        parent::__construct($message, 1650, $previous);
    }
}
