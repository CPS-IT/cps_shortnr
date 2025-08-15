<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Event;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;

class ShortNrPatternParserBootEvent
{
    private ?TypeRegistry $typeRegistry = null;
    /**
     * @return TypeRegistry|null
     */
    public function getTypeRegistry(): ?TypeRegistry
    {
        return $this->typeRegistry;
    }

    /**
     * @param TypeRegistry|null $typeRegistry
     */
    public function setTypeRegistry(?TypeRegistry $typeRegistry): void
    {
        $this->typeRegistry = $typeRegistry;
    }
}
