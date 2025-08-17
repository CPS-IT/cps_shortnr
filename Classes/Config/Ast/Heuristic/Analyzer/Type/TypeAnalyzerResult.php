<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\Type;

class TypeAnalyzerResult
{

    /**
     * @param int $minLen Minimum length for this type
     * @param int|null $maxLen Maximum length for this type (null = unlimited)
     * @param array<int, true> $allowedChars ASCII values of allowed characters
     * @param bool $canBeEmpty Whether this type can be empty string
     */
    public function __construct(
        private readonly int $minLen,
        private readonly ?int $maxLen,
        private readonly array $allowedChars,
        private readonly bool $canBeEmpty = false
    ) {}

    public function getMinLen(): int
    {
        return $this->minLen;
    }

    public function getMaxLen(): ?int
    {
        return $this->maxLen;
    }

    public function getAllowedChars(): array
    {
        return $this->allowedChars;
    }

    public function canBeEmpty(): bool
    {
        return $this->canBeEmpty;
    }
}
