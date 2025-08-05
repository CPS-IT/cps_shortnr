<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\PlatformAdapter\DTO\TreeProcessor;

interface TreeProcessorResultItemInterface
{
    /**
     * flag if this item already has data initialized
     * unserialized objects are always NOT shadow
     *
     * @internal
     * @return bool
     */
    public function isShadow(): bool;
    /**
     * @internal
     * @param mixed $data
     */
    public function setData(mixed $data): void;

    /**
     * @return mixed
     */
    public function getData(): mixed;

    /**
     * @return int
     */
    public function getPrimaryId(): int;

    /**
     * @return int|null
     */
    public function getLanguageId(): ?int;

    /**
     * @internal
     * @param int|null $languageId
     * @return TreeProcessorResultItemInterface
     */
    public function setLanguageId(?int $languageId): TreeProcessorResultItemInterface;

    /**
     * @internal
     * @param TreeProcessorResultItemInterface ...$child
     */
    public function addChild(TreeProcessorResultItemInterface ...$child): void;

    /**
     * @internal
     * @param TreeProcessorResultItemInterface $reference
     * @param int $languageId
     * @return void
     */
    public function addLanguageReference(TreeProcessorResultItemInterface $reference, int $languageId): void;

    /**
     * @return iterable<TreeProcessorResultItemInterface>
     */
    public function getChildren(): iterable;

    /**
     * @return TreeProcessorResultItemInterface|null
     */
    public function getParent(): ?TreeProcessorResultItemInterface;

    /**
     * Fast O(1) root access via cached reference
     * @return TreeProcessorResultItemInterface
     */
    public function getRoot(): TreeProcessorResultItemInterface;

    /**
     * Get translation for specific language ID
     * @param int $languageId
     * @return TreeProcessorResultItemInterface|null
     */
    public function getTranslation(int $languageId): ?TreeProcessorResultItemInterface;

    /**
     * Get all available translations as [languageId => TreeProcessorResultItemInterface]
     * @return array<int, TreeProcessorResultItemInterface>
     */
    public function getAllTranslations(): array;

    /**
     * Check if translation exists for language ID
     * @param int $languageId
     * @return bool
     */
    public function hasTranslation(int $languageId): bool;

    /**
     * Get all available language IDs
     * @return int[]
     */
    public function getAvailableLanguageIds(): array;

    /**
     * Get the base translation item (language 0 equivalent)
     * Returns self if already base, otherwise returns the base item
     * @return TreeProcessorResultItemInterface
     */
    public function getBaseTranslation(): TreeProcessorResultItemInterface;
}
