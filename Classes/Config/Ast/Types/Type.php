<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\TypeConstraint;
use CPSIT\ShortNr\Exception\ShortNrPatternTypeException;
use Generator;
use InvalidArgumentException;

abstract class Type implements TypeInterface
{
    /**
     * @var array<string, TypeConstraint>
     */
    private array $constraints = [];
    /**
     * @var array<string, string> Type-specific constraints arguments
     */
    private array $constraintsArgument = [];
    /**
     * @var string[]
     */
    protected array $name = [];
    protected string $pattern = '';
    protected array $characterClasses = [];

    /**
     * prevent stateful problem with typeRegistry that gives always the same object (no clone object back)
     * @return void
     */
    public function __clone()
    {
        $this->constraintsArgument = [];
    }

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
     * Get the name of this type. (include all aliases)
     *
     * @return string[] The type names (e.g., 'int', 'str')
     */
    public function getName(): array
    {
        return $this->name;
    }

    /**
     * @return string
     * @throws ShortNrPatternTypeException
     */
    public function getDefaultName(): string
    {
        return array_values($this->name)[0] ?? throw new ShortNrPatternTypeException('No Name in type\''. static::class .'\' defined', '');
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function parseValue(mixed $value): mixed
    {
        foreach ($this->constraintsArgument as $name => $cValue) {
            $value = $this->getConstraint($name)?->parseValue($value, $cValue) ?? $value;
        }

        return $value;
    }

    /**
     * Convert a string match to the appropriate PHP type.
     *
     * @param mixed $value The matched string
     * @return mixed The converted value
     * @throws InvalidArgumentException When validation fails
     */
    public function serialize(mixed $value): string
    {
        foreach ($this->constraintsArgument as $name => $cValue) {
            $value = $this->getConstraint($name)?->serialize($value, $cValue) ?? $value;
        }

        return (string)$value;
    }

    /**
     * @return array<string, string> Type-specific constraints arguments
     */
    public function getConstraintsArgument(): array
    {
        return $this->constraintsArgument;
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

    /**
     * @inheritDoc
     */
    public function getConstrainedPattern(): string
    {
        $pattern = $this->getPattern();
        $constraints = $this->constraintsArgument;
        
        // Apply constraints in a specific order to handle dependencies
        // First apply constraints that establish bounds (maxLen, max)
        $boundingConstraints = ['max', 'maxLen'];
        $refinementConstraints = ['min', 'minLen'];
        
        foreach ($boundingConstraints as $name) {
            if (isset($constraints[$name])) {
                $pattern = $this->getConstraint($name)?->modifyPattern($pattern, $constraints[$name]) ?? $pattern;
            }
        }
        
        foreach ($refinementConstraints as $name) {
            if (isset($constraints[$name])) {
                $pattern = $this->getConstraint($name)?->modifyPattern($pattern, $constraints[$name]) ?? $pattern;
            }
        }
        
        // Apply any remaining constraints
        foreach ($constraints as $name => $cValue) {
            if (!in_array($name, array_merge($boundingConstraints, $refinementConstraints))) {
                $pattern = $this->getConstraint($name)?->modifyPattern($pattern, $cValue) ?? $pattern;
            }
        }
        
        return $pattern;
    }

    /**
     * @param array $constraintArguments
     * @return $this
     */
    public function setConstraintArguments(array $constraintArguments): static
    {
        $this->constraintsArgument = $constraintArguments;
        return $this;
    }

    abstract public function applyBoundary(string $pattern, ?string $boundary): string;

    /**
     * @inheritDoc
     */
    abstract public function isGreedy(): bool;
}
