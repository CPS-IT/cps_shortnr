<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

interface FieldConditionInterface
{
    /**
     * @return string
     */
    public function getFieldName(): string;

    /**
     * @return mixed
     */
    public function getCondition(): mixed;

    /**
     * @return mixed
     */
    public function getRawCondition(): mixed;

    /**
     * @param string|null $path
     * @param int $matchIndex
     * @param mixed $value
     * @return FieldConditionMatch
     */
    public function addMatch(?string $path, int $matchIndex, mixed $value): FieldConditionMatch;

    /**
     * @return array<FieldConditionMatch>
     */
    public function getMatches(): array;
}
