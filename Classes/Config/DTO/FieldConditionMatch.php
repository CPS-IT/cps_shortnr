<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

class FieldConditionMatch
{
    private bool $isInitialized = false;

    public function __construct(
        private mixed $value,
        private readonly int $idx,
        private readonly ?string $path,
    )
    {}

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getIdx(): int
    {
        return $this->idx;
    }

    /**
     * @return string
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param mixed $value
     */
    public function setValue(mixed $value): void
    {
        $this->isInitialized = true;
        $this->value = $value;
    }

    /**
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }
}
