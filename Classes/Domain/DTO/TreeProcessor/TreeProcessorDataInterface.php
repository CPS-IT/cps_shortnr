<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\DTO\TreeProcessor;

interface TreeProcessorDataInterface
{
    /**
     * @param mixed $data
     * @return int
     */
    public function getPrimaryIdFromData(mixed $data): int;

    /**
     * @param mixed $data
     * @return int
     */
    public function getRelationIdFromData(mixed $data): int;

    /**
     * @return iterable
     */
    public function getData(): iterable;

    /**
     * @return TreeProcessorResultInterface
     */
    public function getResult(): TreeProcessorResultInterface;
}
