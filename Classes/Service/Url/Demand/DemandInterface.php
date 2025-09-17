<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use Psr\Http\Message\ServerRequestInterface;

interface DemandInterface
{
    /**
     * @return ServerRequestInterface|null
     */
    public function getRequest(): ?ServerRequestInterface;

    /**
     * @param ServerRequestInterface|null $request
     * @return Demand
     */
    public function setRequest(?ServerRequestInterface $request): static;
}
