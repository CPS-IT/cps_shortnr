<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\TypeNodeInterface;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\DefaultConstraint;
use CPSIT\ShortNr\Config\Ast\Types\TypeInterface;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternConstraintException;
use CPSIT\ShortNr\Exception\ShortNrPatternException;
use CPSIT\ShortNr\Exception\ShortNrPatternGenerationException;
use CPSIT\ShortNr\Exception\ShortNrPatternTypeException;

final class GroupNode extends NamedAstNode implements TypeNodeInterface
{
    private string $groupId = '';
    private readonly TypeRegistry $typeRegistry;
    private readonly TypeInterface $type;

    /**
     * @throws ShortNrPatternTypeException
     * @throws ShortNrPatternException
     */
    public function __construct(
        private readonly string $name, // variable name
        string $typeName, // concrete str / string / int / integer ...
        array  $constraints = [], // ['constraintName' => 'value', ...]
        ?TypeRegistry $typeRegistry = null
    ) {
        $this->typeRegistry = $typeRegistry ?? throw new ShortNrPatternException("TypeRegistry not provided in GroupNode");
        $this->type = $this->typeRegistry->getTypeObject($typeName, $constraints);
    }

    /**
     * @return void
     * @throws ShortNrPatternConstraintException|ShortNrPatternTypeException
     */
    public function validateTreeContext(): void
    {
        if (!$this->isOptional() && ($constraint = $this->type->getConstraint(DefaultConstraint::NAME)) !== null) {
            $defaultValue = $constraint->getValue();

            throw new ShortNrPatternConstraintException(
                "Default constraint cannot be used on required group '$this->name'. " .
                "Make the group optional: {".$this->name.":".$this->type->getName()."(". DefaultConstraint::NAME ."=$defaultValue)}? or place it in an optional section: (-{".$this->name.":".$this->type->getName()."(". DefaultConstraint::NAME ."=$defaultValue)})",
                $this->name,
                $defaultValue,
                DefaultConstraint::NAME
            );
        }
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
     */
    public function getType(): TypeInterface
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isGreedy(): bool
    {
        return $this->type->isGreedy();
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

        $validatedValue = $this->type->parseValue($values[$this->name]);
        return $this->type->serialize($validatedValue);
    }

    /**
     * @return array
     * @throws ShortNrPatternTypeException
     */
    public function toArray(): array
    {
        return [
            'regex' => $this->toRegex(),
            'name' => $this->name,
            'type' => $this->getNodeType(),
            'type_name' => $this->type->getName(),
            'type_constraints' => $this->type->getConstraintArguments(),
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
            $data['type_name'],
            $data['type_constraints'] ,
            $typeRegistry ?? throw new ShortNrPatternException("TypeRegistry not provided in GroupNode at Hydration")
        );
        $node->setRegex($data['regex'] ?? null);
        $node->setGroupId($data['groupId']);
        return $node;
    }
}
