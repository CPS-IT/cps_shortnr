<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

/**
 * Minimal interface for all AST nodes.
 * Additional capabilities are provided by composing other interfaces.
 */
interface AstNodeInterface extends 
    NodeTreeInterface,
    RegexGeneratorInterface,
    ValueGeneratorInterface,
    NodeSerializerInterface,
    ValidationInterface
{
    public function hasOptional(): bool;
}