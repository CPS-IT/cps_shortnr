<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition\Operators\DTO;

class ResultOperatorContext extends OperatorContext
{
    /**
     * @param iterable<array> $results
     * @param string $tableName
     * @param array<string, string|int|mixed|array> $condition
     * @param array $existingFields
     */
    public function __construct(
        private readonly iterable $results,
        string $tableName,
        array $condition,
        array $existingFields
    )
    {
        parent::__construct($tableName, $condition, $existingFields);
    }

    /**
     * @return iterable<array>
     */
    public function getResults(): iterable
    {
        return $this->results;
    }
}
