<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface RegexGeneratorInterface
{
    public function toRegex(): string;
}