<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\Interfaces\TypeConstraint;

abstract class BaseConstraint implements TypeConstraint
{
    public function __construct(
        protected readonly mixed $value
    )
    {}

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
