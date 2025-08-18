<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\ConstraintRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternTypeException;

/**
 * Type Registry - regular class with dependency injection
 */
final class TypeRegistry
{
    /**
     * @var array<string, string> lookup Map of [TypeNames => TypeClass]
     */
    private array $types = [];
    private readonly ConstraintRegistry $constraintRegistry;

    /**
     * @throws ShortNrPatternTypeException
     */
    public function __construct(bool $registerDefaults = true)
    {
        if ($registerDefaults) {
            $this->registerDefaults();
        }
        $this->constraintRegistry = new ConstraintRegistry();
    }

    /**
     * @param string $name
     * @param array $arguments [ConstraintName => value]
     * @return TypeInterface
     * @throws ShortNrPatternTypeException
     */
    public function getTypeObject(string $name, array $arguments): TypeInterface
    {
        return match(false) {
            $typeClass = $this->types[$name] ?? false => throw new ShortNrPatternTypeException('Type not found', $name),
            class_exists($typeClass) => throw new ShortNrPatternTypeException('Type \''. $typeClass .'\' is not a CLASS or not exists', $name),
            is_a($typeClass, TypeInterface::class, true) => throw new ShortNrPatternTypeException('Type \''. $typeClass .'\' must implement ' . TypeInterface::class, $name),
            default => new $typeClass($this->constraintRegistry, $arguments)
        };
    }

    /**
     * @param string $class
     * @param string[] $names
     * @return void
     * @throws ShortNrPatternTypeException
     */
    public function registerType(string $class, array $names): void
    {
        foreach ($names as $name) {
            if (!is_a($class, TypeInterface::class, true)) {
                throw new ShortNrPatternTypeException('TypeClass '. $class .' must be implement '. TypeInterface::class, $name);
            }
            // overwrite types if needed
            $this->types[$name] = $class;
        }
    }

    /**
     * @throws ShortNrPatternTypeException
     */
    private function registerDefaults(): void
    {
        $this->registerType(IntType::class, IntType::getNames());
        $this->registerType(StringType::class, StringType::getNames());
        // Add more default types here
    }
}
