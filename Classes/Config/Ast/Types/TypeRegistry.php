<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Exception\ShortNrPatternTypeException;

/**
 * Type Registry - regular class with dependency injection
 */
final class TypeRegistry
{
    private array $types = [];

    /**
     * @throws ShortNrPatternTypeException
     */
    public function __construct(bool $registerDefaults = true)
    {
        if ($registerDefaults) {
            $this->registerDefaults();
        }
    }

    /**
     * @param string $name
     * @return TypeInterface|null
     * @throws ShortNrPatternTypeException
     */
    public function getType(string $name): ?TypeInterface
    {
        // Clone to ensure each GroupNode gets its own type instance
        // with isolated constraint state
        return clone ($this->types[$name] ?? throw new ShortNrPatternTypeException('Type not found', $name));
    }

    /**
     * @throws ShortNrPatternTypeException
     */
    public function registerType(TypeInterface $type): void
    {
        foreach ($type->getName() as $name) {
            $currentType = $this->types[$name] ?? null;
            $this->types[$name] = match($currentType) {
                null =>  $type,
                $type => $currentType,
                default => throw new ShortNrPatternTypeException(
                    'Type registration conflict: trying to register type ('. $type::class .') with typeName \''. $name .'\' while a different type ('. $currentType::class .') is already registered under that name.',
                    $name
                )
            };
        }
    }

    /**
     * Get all registered type names
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->types);
    }

    /**
     * @throws ShortNrPatternTypeException
     */
    private function registerDefaults(): void
    {
        $this->registerType(new IntType());
        $this->registerType(new StringType());
        // Add more default types here
    }
}
