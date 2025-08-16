<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface NestedNodeInterface extends AstNodeInterface
{
    public function addChild(AstNodeInterface $node): void;
    
    /**
     * @return AstNodeInterface[]
     */
    public function getChildren(): array;
}