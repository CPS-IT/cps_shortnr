<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

use CPSIT\ShortNr\Config\Ast\Types\TypeInterface;
use CPSIT\ShortNr\Exception\ShortNrPatternParseException;

interface TypeNodeInterface extends NamedNodeInterface, NodeGroupAwareInterface
{
    /**
     * @return TypeInterface
     * @throws ShortNrPatternParseException
     */
    public function getType(): TypeInterface;

    /**
     * @return string type var name
     */
    public function getName(): string;

    /**
     * @internal
     * @param string $id
     * @return void
     */
    public function setGroupId(string $id): void;

    /**
     * internal group ID
     * @return string
     */
    public function getGroupId(): string;

    public function isGreedy(): bool;
}
