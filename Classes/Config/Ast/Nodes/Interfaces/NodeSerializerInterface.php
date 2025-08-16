<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;

interface NodeSerializerInterface
{
    public function toArray(): array;
    public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static;
}