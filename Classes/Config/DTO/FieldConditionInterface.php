<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

interface FieldConditionInterface
{
    /**
     * return true if there is ANY match placeholder in the Config Conditions
     *
     * @return bool
     */
    public function hasMatches(): bool;

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
     * @return array<FieldConditionMatch>
     */
    public function getMatches(): array;

    /**
     * @return array<FieldConditionMatch>
     */
    public function getProcessedMatches(): array;

    /**
     * expect a clean ID => VALUE list (no nested array from REGEX PARSER)
     * return if any matching was processed
     *
     * @param array $matches
     * @return bool
     */
    public function processMatches(array $matches): bool;
}
