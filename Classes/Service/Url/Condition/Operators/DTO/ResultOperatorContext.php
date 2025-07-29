<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators\DTO;

class ResultOperatorContext extends OperatorContext
{
    /**
     * @param iterable<array> $results
     */
    public function __construct(
        private readonly iterable $results
    )
    {}

    /**
     * @return iterable<array>
     */
    public function getResults(): iterable
    {
        return $this->results;
    }
}
