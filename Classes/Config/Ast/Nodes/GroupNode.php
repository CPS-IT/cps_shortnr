<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternException;
use CPSIT\ShortNr\Exception\ShortNrPatternGenerationException;
use CPSIT\ShortNr\Exception\ShortNrPatternConstraintException;
use RuntimeException;

final class GroupNode extends NamedAstNode implements TypeRegistryAwareInterface
{
    private string $groupId = '';
    private ?TypeRegistry $typeRegistry = null;

    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly array $constraints = []
    ) {
        // Note: Validation moved to validateTreeContext() which is called after tree construction
    }

    /**
     * Validate default constraint usage after tree structure is complete.
     * This must be called after all parent-child relationships are established.
     */
    public function validateTreeContext(): void
    {
        if (!$this->isOptional() && isset($this->constraints['default'])) {
            $defaultValue = $this->constraints['default'];
            throw new ShortNrPatternConstraintException(
                "Default constraint cannot be used on required group '{$this->name}'. " .
                "Make the group optional: {{$this->name}:{$this->type}(default={$defaultValue})}? or place it in an optional section: (-{{$this->name}:{$this->type}(default={$defaultValue})})",
                $this->name,
                $defaultValue,
                'invalid_default_usage'
            );
        }
    }

    public function setTypeRegistry(TypeRegistry $registry): void
    {
        $this->typeRegistry = $registry;
    }

    public function getTypeRegistry(): ?TypeRegistry
    {
        return $this->typeRegistry;
    }

    public function setGroupId(string $id): void
    {
        $this->groupId = $id;
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }


    protected function generateRegex(): string
    {
        if ($this->typeRegistry === null) {
            throw new ShortNrPatternException("TypeRegistry not set on GroupNode");
        }

        $typeObj = $this->typeRegistry->getType($this->type);
        if (!$typeObj) {
            throw new \InvalidArgumentException("Could not resolve type: $this->type");
        }

        // Use constraint-aware pattern generation
        $pattern = $typeObj->getConstrainedPattern($this->constraints);
        return '(?P<' . $this->groupId . '>' . $pattern . ')';
    }


    public function generate(array $values): string
    {
        if (!array_key_exists($this->name, $values)) {
            if ($this->isOptional()) {
                return '';
            }
            throw new ShortNrPatternGenerationException(
                "Missing required value for group",
                $values,
                $this->name
            );
        }
        
        // Validate and parse the value using the type system
        if ($this->typeRegistry === null) {
            throw new ShortNrPatternException("TypeRegistry not set on GroupNode");
        }
        
        $typeObj = $this->typeRegistry->getType($this->type);
        if (!$typeObj) {
            throw new \InvalidArgumentException("Could not resolve type: $this->type");
        }
        
        // This will trigger type validation and proper casting
        $validatedValue = $typeObj->parseValue($values[$this->name], $this->constraints);
        
        return (string)$validatedValue;
    }

    public function getGroupNames(): array
    {
        return [$this->name];
    }


    public function getNodeType(): string
    {
        return 'group';
    }

    /**
     * Check if this group is greedy (consumes as much as possible).
     */
    public function isGreedy(): bool
    {
        if ($this->typeRegistry === null) {
            return false; // Safe default
        }

        $typeObj = $this->typeRegistry->getType($this->type);
        if (!$typeObj) {
            return false; // Safe default
        }

        return $typeObj->isGreedy($this->constraints);
    }

    public function toArray(): array
    {
        return [
            'regex' => $this->toRegex(),
            'type' => $this->getNodeType(),
            'name' => $this->name,
            'dataType' => $this->type,
            'constraints' => $this->constraints,
            'groupId' => $this->groupId
        ];
    }

    /**
     * @param array $data
     * @param TypeRegistry|null $typeRegistry
     * @return static
     */
    public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static
    {
        $node = new self(
            $data['name'],
            $data['dataType'],
            $data['constraints']
        );
        $node->setRegex($data['regex'] ?? null);
        $node->setGroupId($data['groupId']);

        if ($typeRegistry !== null) {
            $node->setTypeRegistry($typeRegistry);
        }

        return $node;
    }
}
