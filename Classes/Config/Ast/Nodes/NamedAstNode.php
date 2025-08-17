<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NamedNodeInterface;

abstract class NamedAstNode extends AstNode implements NamedNodeInterface
{
    /**
     * Get the node type name for serialization
     */
    abstract public function getNodeType(): string;
}
