<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer;

class AnalyzerResult
{
    public const MAX_LEN_LIMIT = 10_000;
    private readonly int $maxLen;

    /**
     * @param int $minLen // min expected Length of the incoming string
     * @param int|null $maxLen // max expected Length of the incoming string, NULL mean NO length limit (the internal limit is the MAX LEN of 9999), must be Equal or greater than MinLen
     * @param array<string, bool> $literals string key map ['TEXT' => true/false], contains optional and required literals, optional are false required are true
     * @param array<int, true> $allowedChars ascii key map [ASCII_NUM => true]
     * @param string|null $prefix expected Prefix (is a literal, at the beginning), NULL no literal at the beginning found
     * @param string|null $suffix expected Suffix (is a literal, at the end), NULL no literal at the end found
     */
    public function __construct(
        private readonly int $minLen = 0,
        ?int $maxLen = null,
        private readonly array $literals = [],
        private readonly array $allowedChars = [],
        private readonly ?string $prefix = null,
        private readonly ?string $suffix = null
    )
    {
        // must be greater or equal then MinLen
        $this->maxLen = max(
            $minLen,
            // if null use SYSTEM MAX_LEN_LIMIT
            $maxLen === null
                ? self::MAX_LEN_LIMIT
                // if greater than SYSTEM MAX_LEN_LIMIT, use SYSTEM MAX_LEN_LIMIT
                : min($maxLen, self::MAX_LEN_LIMIT)
        );
    }

    /**
     * @return int
     */
    public function getMinLen(): int
    {
        return $this->minLen;
    }

    /**
     * @return int
     */
    public function getMaxLen(): int
    {
        return $this->maxLen;
    }

    /**
     * @return array<string, bool>
     */
    public function getLiterals(): array
    {
        return $this->literals;
    }

    /**
     * @return array
     */
    public function getAllowedChars(): array
    {
        return $this->allowedChars;
    }

    /**
     * @return string|null
     */
    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    /**
     * @return string|null
     */
    public function getSuffix(): ?string
    {
        return $this->suffix;
    }
}
