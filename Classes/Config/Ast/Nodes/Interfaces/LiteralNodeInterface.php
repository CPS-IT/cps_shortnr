<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface LiteralNodeInterface extends NamedNodeInterface, BoundaryProviderInterface
{
    /**
     * @return string content of the literal node
     */
    public function getText(): string;
}
