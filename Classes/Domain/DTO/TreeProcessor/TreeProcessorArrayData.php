<?php declare(strict_types=1);


namespace CPSIT\ShortNr\Domain\DTO\TreeProcessor;

use CPSIT\ShortNr\Exception\ShortNrTreeProcessorException;

class TreeProcessorArrayData implements TreeProcessorDataInterface
{
    /**
     * TreeProcessorData constructor.
     * @param int|string $primaryKey
     * @param int|string|null $relationKey
     * @param array[] $data
     * @throws ShortNrTreeProcessorException
     */


    public function __construct(
        private readonly int|string $primaryKey,
        private readonly int|string|null $relationKey,
        private readonly iterable $data
    ) {
        if (!$this->validateTreeData($primaryKey, $relationKey, $data)) {
            throw new ShortNrTreeProcessorException('Invalid primary key or relation key');
        }
    }

    /**
     * overwrite to replace result object
     * @return TreeProcessorGeneratorInterface
     */
    protected function getResultObject(): TreeProcessorGeneratorInterface
    {
        return new TreeProcessorResult();
    }

    /**
     * @param int|string $primaryKey
     * @param int|string|null $relationKey
     * @param iterable $data
     * @return bool
     */
    private function validateTreeData(int|string $primaryKey, int|string|null $relationKey, iterable $data): bool
    {
        // Check if data is empty or get first item
        $firstItem = null;
        foreach ($data as $item) {
            $firstItem = $item;
            break;
        }

        // Must have at least one item and primary key must exist
        return is_array($firstItem) &&
               array_key_exists($primaryKey, $firstItem);
    }

    /**
     * @param mixed $data
     * @return int
     */
    public function getPrimaryIdFromData(mixed $data): int
    {
        return (int)($data[$this->primaryKey] ?? 0);
    }

    /**
     * @param mixed $data
     * @return int
     */
    public function getRelationIdFromData(mixed $data): int
    {
        return (int)($data[$this->relationKey] ?? 0);
    }

    /**
     * @return iterable
     */
    public function getData(): iterable
    {
        return $this->data;
    }

    public function getResult(): TreeProcessorResultInterface
    {
        $tree = $this->getResultObject();
        foreach ($this->getData() as $subItem) {
            $tree->processData($this, $subItem);
        }
        return $tree;
    }
}
