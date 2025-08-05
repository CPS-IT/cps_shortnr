<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\DataProvider\DTO;

class PageData
{
    public function __construct(
        private readonly int $uid,
        private readonly int $languageId,
        private readonly string $slug
    )
    {}

    /**
     * @return int
     */
    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * @return int
     */
    public function getLanguageId(): int
    {
        return $this->languageId;
    }

    /**
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }
}
