<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface NodeTreeInterface
{
    public function setParent(?NodeTreeInterface $parent): void;
    public function getParent(): ?NodeTreeInterface;
    public function isOptional(): bool;
}