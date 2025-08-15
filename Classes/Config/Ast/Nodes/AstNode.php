<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use RuntimeException;

abstract class AstNode
{
    private ?string $regex = null;


    /**
     * Convert this node to its regex representation.
     */
    public function toRegex(): string
    {
        return $this->regex ??= $this->generateRegex();
    }

    /**
     * @param string|null $regex
     */
    protected function setRegex(?string $regex): void
    {
        $this->regex = $regex;
    }

    /**
     * regex generator
     * @return string
     */
    abstract protected function generateRegex(): string;

    /**
     * Generate a string representation using the provided values.
     *
     * @param array<string, mixed> $values Associative array of group names to values
     * @throws RuntimeException When required values are missing
     */
    abstract public function generate(array $values): string;

    /**
     * Get all group names defined in this node and its children.
     *
     * @return list<string> List of group names
     */
    abstract public function getGroupNames(): array;

    /**
     * Check if this node contains optional elements.
     */
    abstract public function hasOptional(): bool;

    /**
     * Convert this node to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'regex' => $this->toRegex()
        ];
    }

    /**
     * Create node from array data.
     */
    abstract public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static;
}
