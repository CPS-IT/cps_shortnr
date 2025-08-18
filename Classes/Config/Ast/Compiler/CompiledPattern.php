<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Compiler;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternTypeException;
use InvalidArgumentException;
use Throwable;

final class CompiledPattern
{
    public function __construct(
        private readonly string $pattern,
        private readonly string $regex,
        private readonly AstNodeInterface $ast,
        private readonly array $namedGroups,
        private readonly array $groupTypes,
        private readonly array $groupConstraints,
        private readonly TypeRegistry $typeRegistry
    ) {}

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getRegex(): string
    {
        return $this->regex;
    }

    public function getAst(): AstNodeInterface
    {
        return $this->ast;
    }

    public function getNamedGroups(): array
    {
        return $this->namedGroups;
    }

    public function getGroupTypes(): array
    {
        return $this->groupTypes;
    }

    public function getGroupConstraints(): array
    {
        return $this->groupConstraints;
    }

    /**
     * @throws ShortNrPatternTypeException
     */
    public function match(string $input): ?MatchResult
    {
        // Empty input should not match patterns that consist entirely of optional groups
        if ($input === '') {
            return null;
        }
        
        if (!preg_match($this->regex, $input, $matches)) {
            return null;
        }

        $result = new MatchResult($input);

        foreach ($this->namedGroups as $groupId => $groupName) {
            // Check if group matched (empty string is valid for string types)
            $hasValue = isset($matches[$groupId]);

            if ($hasValue && $matches[$groupId] !== '') {
                $rawValue = $matches[$groupId];
                $type = $this->groupTypes[$groupName];
                $constraints = $this->groupConstraints[$groupName];
                $typeHandler = $this->typeRegistry->getTypeObject($type, $constraints);

                // Get the type handler
                try {
                    // Process value with type and constraints
                    $processedValue = $typeHandler->parseValue($rawValue);
                    $result->addGroup($groupName, $processedValue, $type, $constraints);
                } catch (Throwable $e) {
                    $result->addError($e);
                }
            } else {
                // Handle missing optional groups with default constraints
                $type = $this->groupTypes[$groupName];
                $constraints = $this->groupConstraints[$groupName];
                
                if (isset($constraints['default'])) {
                    // Get the type handler
                    $typeHandler = $this->typeRegistry->getTypeObject($type, $constraints);

                    try {
                        // Process null value to trigger default
                        $processedValue = $typeHandler->parseValue(null);
                        $result->addGroup($groupName, $processedValue, $type, $constraints);
                    } catch (InvalidArgumentException $e) {
                        $result->addError($e);
                    }
                }
            }
        }

        // Return result even if constraints failed - caller can check isFailed()
        return $result;
    }

    public function generate(array $values): string
    {
        // Extract just the values if we're given the full group data
        $cleanValues = [];
        foreach ($values as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                $cleanValues[$key] = $value['value'];
            } else {
                $cleanValues[$key] = $value;
            }
        }
        return $this->ast->generate($cleanValues);
    }
}
