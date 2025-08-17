<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\TypeNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\TypeRegistryAwareInterface;
use CPSIT\ShortNr\Config\Ast\Types\TypeInterface;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternConstraintException;
use CPSIT\ShortNr\Exception\ShortNrPatternException;
use CPSIT\ShortNr\Exception\ShortNrPatternGenerationException;
use CPSIT\ShortNr\Exception\ShortNrPatternParseException;

final class GroupNode extends NamedAstNode implements TypeNodeInterface, TypeRegistryAwareInterface
{
    private string $groupId = '';
    private ?TypeRegistry $typeRegistry = null;
    private ?TypeInterface $type = null;

    public function __construct(
        private readonly string $name, // variable name
        private readonly string $typeName, // concrete str / string / int / integer ...
        private readonly array  $constraints = [] // ['constraintName' => 'value', ...]
    ) {}

    public function validateTreeContext(): void
    {
        if (!$this->isOptional() && isset($this->constraints['default'])) {
            $defaultValue = $this->constraints['default'];
            throw new ShortNrPatternConstraintException(
                "Default constraint cannot be used on required group '$this->name'. " .
                "Make the group optional: {".$this->name.":".$this->typeName."(default=$defaultValue)}? or place it in an optional section: (-{".$this->name.":".$this->typeName."(default=$defaultValue)})",
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

    /**
     * @return TypeRegistry
     * @throws ShortNrPatternException
     */
    public function getTypeRegistry(): TypeRegistry
    {
        return $this->typeRegistry ?? throw new ShortNrPatternException("TypeRegistry not set on GroupNode");
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
     * @return TypeInterface
     * @throws ShortNrPatternException
     * @throws ShortNrPatternParseException
     */
    public function getType(): TypeInterface
    {
        return $this->type ??= $this->getTypeRegistry()->getType($this->typeName)->setConstraintArguments($this->constraints) ?? throw new ShortNrPatternParseException(
            'Could not find Type \''.$this->typeName.'\'',
            ''
        );
    }

    /**
     * @return bool
     * @throws ShortNrPatternException
     * @throws ShortNrPatternParseException
     */
    public function isGreedy(): bool
    {
        return $this->getType()->isGreedy();
    }

    /**
     * @param string $id
     * @return void
     */
    public function setGroupId(string $id): void
    {
        $this->groupId = $id;
    }

    /**
     * @return string
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     * @throws ShortNrPatternException
     * @throws ShortNrPatternParseException
     */
    protected function generateRegex(): string
    {
        $type = $this->getType();

        // Get the base pattern with constraints applied
        $pattern = $type->getConstrainedPattern();

        // Let the type handle boundary application if it's greedy
        if ($type->isGreedy()) {
            $boundary = $this->determineBoundary();
            $pattern = $type->applyBoundary($pattern, $boundary);
        }

        return '(?P<' . $this->groupId . '>' . $pattern . ')';
    }

    /**
     * @return string|null
     */
    private function determineBoundary(): ?string
    {
        // Simplified - just determine WHAT the boundary is, not HOW to apply it
        $parent = $this->getParent();

        if ($parent instanceof SubSequenceNode) {
            // Inside subsequence: only look within
            return $this->calculateNextBoundary();
        }

        // Regular sequence: look for any next boundary
        return $this->getNextBoundary();
    }

    /**
     * @param array $values
     * @return string
     * @throws ShortNrPatternException
     * @throws ShortNrPatternGenerationException
     * @throws ShortNrPatternParseException
     */
    public function generate(array $values): string
    {
        if (!array_key_exists($this->name, $values)) {
            if ($this->isOptional()) {
                // Check for default constraint
                if (isset($this->constraints['default'])) {
                    $values[$this->name] = $this->constraints['default'];
                } else {
                    return '';
                }
            } else {
                throw new ShortNrPatternGenerationException(
                    "Missing required value for group",
                    $values,
                    $this->name
                );
            }
        }

        $type = $this->getType();
        $validatedValue = $type->parseValue($values[$this->name]);
        return $type->serialize($validatedValue);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'regex' => $this->toRegex(),
            'type' => $this->getNodeType(),
            'name' => $this->name,
            'dataType' => $this->typeName,
            'constraints' => $this->constraints,
            'groupId' => $this->groupId
        ];
    }

    /**
     * @param array $data
     * @param TypeRegistry|null $typeRegistry
     * @return static
     * @throws ShortNrPatternException
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
        } else {
            throw new ShortNrPatternException("TypeRegistry not provided in GroupNode at Hydration");
        }

        return $node;
    }
}
