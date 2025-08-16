<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes\Interfaces;

interface ValueGeneratorInterface
{
    /**
     * @param array<string, mixed> $values
     */
    public function generate(array $values): string;
    
    /**
     * @return list<string>
     */
    public function getGroupNames(): array;
}