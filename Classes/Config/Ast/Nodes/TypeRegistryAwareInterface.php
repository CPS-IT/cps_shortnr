<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;

interface TypeRegistryAwareInterface
{
    public function setTypeRegistry(TypeRegistry $registry): void;
    
    public function getTypeRegistry(): ?TypeRegistry;
}