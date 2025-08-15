<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Pattern\Helper;

interface PatternGroupCounterInterface
{
    public function getCounter(): int;
    public function increaseCounter(): int;
}
