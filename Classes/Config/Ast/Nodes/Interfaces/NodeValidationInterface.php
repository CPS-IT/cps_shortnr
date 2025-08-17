<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface NodeValidationInterface
{
    public function validateEntireTree(): void;

    public function validateTreeContext(): void;
}
