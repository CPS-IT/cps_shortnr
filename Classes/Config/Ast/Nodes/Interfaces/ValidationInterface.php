<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface ValidationInterface
{
    public function validateTreeContext(): void;
    public function validateEntireTree(): void;
}