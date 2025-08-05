<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor\DTO;

use CPSIT\ShortNr\Service\Url\ValidateUriTrait;

class ProcessorDecodeResult implements ProcessorDecodeResultInterface
{
    use ValidateUriTrait;

    /**
     * @param string|null $uri
     */
    public function __construct(
        private readonly ?string $uri
    )
    {}

    /**
     * @return string|null
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return ($this->uri !== null && $this->validateUri($this->uri) !== false);
    }
}
