<?php

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\Interfaces\TypeConstraint;

interface ConstraintAwareInterface
{
    /**
     * @return array<TypeConstraint>
     */
    public function getConstraints(): array;

    public function getConstraint(string $name): ?TypeConstraint;

    /**
     * @internal
     * @return string[] return the supported Constraint classes for that Type
     */
    public function getSupportedConstraintClasses(): array;

    /**
     * Get regex pattern with constraints applied.
     * Returns non-greedy patterns when capping constraints are present.
     */
    public function getConstrainedPattern(): string;
}
