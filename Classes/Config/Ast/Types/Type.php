<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\TypeConstraint;
use Generator;
use InvalidArgumentException;

abstract class Type implements TypeInterface
{
    /**
     * @var array<string, TypeConstraint>
     */
    private array $constraints = [];
    /**
     * @var string[]
     */
    protected array $name = [];
    protected string $pattern = '';
    protected array $characterClasses = [];

    /**
     * Get the regex pattern for this type.
     *
     * @return string The regex pattern
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Get the name of this type.
     *
     * @return string[] The type names (e.g., 'int', 'str')
     */
    public function getName(): array
    {
        return $this->name;
    }

    /**
     * @param mixed $value
     * @param array $constraints
     * @return mixed
     */
    public function parseValue(mixed $value, array $constraints = []): mixed
    {
        foreach ($constraints as $name => $cValue) {
            $value = $this->getConstraint($name)?->parseValue($value, $cValue) ?? $value;
        }

        return $value;
    }

    /**
     * Convert a string match to the appropriate PHP type.
     *
     * @param mixed $value The matched string
     * @param array<string, string> $constraints Type-specific constraints
     * @return mixed The converted value
     * @throws InvalidArgumentException When validation fails
     */
    public function serialize(mixed $value, array $constraints = []): string
    {
        foreach ($constraints as $name => $cValue) {
            $value = $this->getConstraint($name)?->serialize($value, $cValue) ?? $value;
        }

        return (string)$value;
    }

    /**
     * @param TypeConstraint ...$constraint
     * @return void
     */
    protected function registerConstraint(TypeConstraint ...$constraint): void
    {
        foreach ($constraint as $item) {
            $this->constraints[$item->getName()] = $item;
        }
    }

    /**
     * @return Generator<TypeConstraint>
     */
    protected function getConstraints(): Generator
    {
        yield from $this->constraints;
    }

    /**
     * @param string $name
     * @return TypeConstraint|null
     */
    protected function getConstraint(string $name): ?TypeConstraint
    {
        return $this->constraints[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getCharacterClasses(): array
    {
        return $this->characterClasses;
    }
}
