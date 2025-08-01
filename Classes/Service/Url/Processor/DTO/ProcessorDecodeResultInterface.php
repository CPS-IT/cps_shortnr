<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor\DTO;

interface ProcessorDecodeResultInterface extends ProcessorResultInterface
{
    public function getUri(): ?string;
}
