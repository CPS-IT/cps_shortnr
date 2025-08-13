<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators\DTO;

class EncodingOperatorContext extends OperatorContext
{
    /**
     * @param array $data
     */
    public function __construct(
        private readonly array $data
    )
    {}

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
