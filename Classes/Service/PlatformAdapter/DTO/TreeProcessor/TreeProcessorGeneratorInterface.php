<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\PlatformAdapter\DTO\TreeProcessor;

interface TreeProcessorGeneratorInterface extends TreeProcessorResultInterface
{
    /**
     * @param TreeProcessorDataInterface $data
     * @param mixed $item
     */
    public function processData(TreeProcessorDataInterface $data, mixed $item): void;

    /**
     * @param string|int|null $primaryKey
     * @return TreeProcessorGeneratorInterface
     */
    public function setPrimaryKey(string|int|null $primaryKey): TreeProcessorGeneratorInterface;

    /**
     * @param string|int|null $relationKey
     * @return TreeProcessorGeneratorInterface
     */
    public function setRelationKey(string|int|null $relationKey): TreeProcessorGeneratorInterface;

    /**
     * @param string|int|null $languageKey
     * @return TreeProcessorGeneratorInterface
     */
    public function setLanguageKey(string|int|null $languageKey): TreeProcessorGeneratorInterface;

    /**
     * @param string|int|null $languageRelationKey
     * @return TreeProcessorGeneratorInterface
     */
    public function setLanguageRelationKey(string|int|null $languageRelationKey): TreeProcessorGeneratorInterface;

    /**
     * @param int $id
     * @param bool $createIfNotExists
     * @return TreeProcessorResultItemInterface|null
     */
    public function getItem(int $id, bool $createIfNotExists = false): ?TreeProcessorResultItemInterface;

    /**
     * Removes all branches that have no data, and remove all related children too.
     * This method identifies dead branches (nodes with shadow === true) and prunes them.
     *
     * @return void
     */
    public function removeDeadBranches(): void;
}
