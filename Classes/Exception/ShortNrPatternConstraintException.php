<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Exception;

final class ShortNrPatternConstraintException extends ShortNrPatternException 
{
    public function __construct(
        string $message,
        public readonly string $groupName,
        public readonly mixed $value,
        public readonly string $constraintName,
        ?\Throwable $previous = null
    ) {
        parent::__construct("Constraint error in group '$groupName': $message", 0, $previous);
    }
}