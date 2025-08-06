<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\DTO;

/**
 * Represents a successful regex match against a URI with associated config names
 *
 * Contains the matched URI, config names that share the same regex pattern,
 * and the regex capture groups for parameter extraction in processors.
 */
class ConfigMatchCandidate
{
    private array $cache = [];

    public const MATCH_PREFIX_PLACEHOLDER = '{match-';
    public const DEFAULT_MATCH_REGEX = '/'. self::MATCH_PREFIX_PLACEHOLDER .'(\d+)}/';

    /**
     * Create a config match candidate from successful regex matching
     *
     * @param string $shortNrUri The short URI that was matched
     * @param array $names Config names that use the matched regex pattern
     * @param array $matches Regex capture groups with offset information
     */
    public function __construct(
        private readonly string $shortNrUri,
        private readonly array $names,
        private readonly array $matches
    )
    {}

    /**
     * Get config names that are candidates for processing
     *
     * These are config items that share the same regex pattern that matched.
     * Multiple configs can use the same regex for different processing logic.
     *
     * @return array Config names like ['pages', 'events']
     */
    public function getNames(): array
    {
        return $this->names;
    }

    /**
     * Get regex capture groups for parameter extraction
     *
     * Contains the full match and capture groups with offset information
     * from preg_match with PREG_OFFSET_CAPTURE flag.
     * Used by processors to extract IDs and other parameters.
     *
     * @return array Regex matches like [['PAGE123', 0], ['123', 4]]
     */
    public function getMatches(): array
    {
        return $this->matches;
    }

    /**
     * Get the short URI that matched the regex pattern
     *
     * @return string The original short URI like '/PAGE123' or '/EVENT456-2'
     */
    public function getShortNrUri(): string
    {
        return $this->shortNrUri;
    }

    /**
     * extract Value from Matches via MatchGroup Placeholder
     *
     * @param string $matchGroupString
     * @return mixed
     */
    public function getValueFromMatchesViaMatchGroupString(string $matchGroupString): mixed
    {
        if (isset($this->cache['valueExtract'][$matchGroupString])) {
            return $this->cache['valueExtract'][$matchGroupString];
        }

        $value = null;
        $idx = $this->extractIdFromMatchGroupPlaceholder($matchGroupString);
        if ($idx !== null) {
            $value = $this->getMatches()[$idx][0] ?? null;
        }

        return $this->cache['valueExtract'][$matchGroupString] = $value;
    }

    /**
     * extract MatchGroup ID From $matchGroupPlaceholder
     *
     * @param string $matchGroupString
     * @return int|null
     */
    public function extractIdFromMatchGroupPlaceholder(string $matchGroupString): ?int
    {
        if (isset($this->cache['idExtract'][$matchGroupString])) {
            return $this->cache['idExtract'][$matchGroupString];
        }

        $idFound = null;
        if (preg_match(static::DEFAULT_MATCH_REGEX, $matchGroupString, $m) !== false) {
            $idx = $m[1] ?? null;
            if($idx !== null) {
                $idFound = (int)$idx;
            }
        }

        return $this->cache['idExtract'][$matchGroupString] = $idFound;
    }
}
