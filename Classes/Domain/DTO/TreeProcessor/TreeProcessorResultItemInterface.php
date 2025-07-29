<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\DTO\TreeProcessor;

interface TreeProcessorResultItemInterface
{
    /**
     * @param mixed $data
     */
    public function setData(mixed $data): void;

    /**
     * @return mixed
     */
    public function getData(): mixed;

    /**
     * @param TreeProcessorResultItemInterface ...$child
     */
    public function addChild(TreeProcessorResultItemInterface ...$child): void;

    /**
     * @return iterable<TreeProcessorResultItemInterface>
     */
    public function getChildren(): iterable;

    /**
     * @param TreeProcessorResultItemInterface|null $parent
     */
    public function setParent(?TreeProcessorResultItemInterface $parent): void;

    /**
     * @return TreeProcessorResultItemInterface|null
     */
    public function getParent(): ?TreeProcessorResultItemInterface;

    /**
     * Fast O(1) root access via cached reference
     * @return TreeProcessorResultItemInterface
     */
    public function getRoot(): TreeProcessorResultItemInterface;
}
