<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternException;
use CPSIT\ShortNr\Exception\ShortNrPatternGenerationException;
use RuntimeException;

final class GroupNode extends NamedAstNode implements TypeRegistryAwareInterface
{
    private string $groupId = '';
    private ?TypeRegistry $typeRegistry = null;

    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly array $constraints = [],
        private readonly bool $optional = false
    ) {}

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

    public function isOptional(): bool
    {
        return $this->optional;
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

        $pattern = $typeObj->getPattern();
        $regex = '(?P<' . $this->groupId . '>' . $pattern . ')';
        return $this->optional ? $regex . '?' : $regex;
    }


    public function generate(array $values): string
    {
        if (!isset($values[$this->name])) {
            if ($this->optional) {
                return '';
            }
            throw new ShortNrPatternGenerationException(
                "Missing required value for group",
                $values,
                $this->name
            );
        }
        return (string)$values[$this->name];
    }

    public function getGroupNames(): array
    {
        return [$this->name];
    }

    public function hasOptional(): bool
    {
        return $this->optional;
    }

    public function getNodeType(): string
    {
        return 'group';
    }

    public function toArray(): array
    {
        return [
            'regex' => $this->toRegex(),
            'type' => $this->getNodeType(),
            'name' => $this->name,
            'dataType' => $this->type,
            'constraints' => $this->constraints,
            'optional' => $this->optional,
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
            $data['constraints'],
            $data['optional']
        );
        $node->setRegex($data['regex'] ?? null);
        $node->setGroupId($data['groupId']);

        if ($typeRegistry !== null) {
            $node->setTypeRegistry($typeRegistry);
        }

        return $node;
    }
}
