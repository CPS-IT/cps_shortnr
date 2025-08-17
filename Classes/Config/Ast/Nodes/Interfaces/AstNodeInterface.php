<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;

/**
 * Minimal interface for all AST nodes.
 * Additional capabilities are provided by composing other interfaces.
 */
interface AstNodeInterface extends NodeValidationInterface
{
    /**
     * @return string generate Regex once
     */
    public function toRegex(): string;

    /**
     * generate string from assoc data array
     *
     * @param array $values
     * @return string
     */
    public function generate(array $values): string;

    public function isOptional(): bool;
    public function setParent(?NodeTreeInterface $parent): void;
    public function getParent(): ?NodeTreeInterface;
    public function toArray(): array;
    public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static;
}
