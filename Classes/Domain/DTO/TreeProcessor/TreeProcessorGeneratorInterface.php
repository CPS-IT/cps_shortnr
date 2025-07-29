<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\DTO\TreeProcessor;

interface TreeProcessorGeneratorInterface extends TreeProcessorResultInterface
{
    /**
     * @param TreeProcessorDataInterface $data
     * @param mixed $item
     */
    public function processData(TreeProcessorDataInterface $data, mixed $item): void;

    /**
     * @param int $id
     * @param bool $createIfNotExists
     * @return TreeProcessorResultItemInterface|null
     */
    public function getItem(int $id, bool $createIfNotExists = false): ?TreeProcessorResultItemInterface;
}
