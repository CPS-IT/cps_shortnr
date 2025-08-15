<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

abstract class NamedAstNode extends AstNode
{
    /**
     * Get the node type name for serialization
     */
    abstract public function getNodeType(): string;
}