<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor\DTO;

interface ProcessorResultInterface
{
    public function isValid(): bool;
}
