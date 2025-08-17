<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface NodeTreeInterface extends NamedNodeInterface, NodeGroupAwareInterface, BoundaryProviderInterface
{
    /**
     * add a new children
     *
     * @param AstNodeInterface $node
     * @return void
     */
    public function addChild(AstNodeInterface $node): void;

    /**
     * @return array children nodes
     */
    public function getChildren(): array;
}
