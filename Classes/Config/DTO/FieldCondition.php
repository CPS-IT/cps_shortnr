<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use CPSIT\ShortNr\Service\Url\Regex\ArrayPackTrait;
use CPSIT\ShortNr\Service\Url\Regex\MatchResult;

class FieldCondition implements FieldConditionInterface
{
    use ArrayPackTrait;

    private array $conditionResolveCache = [];

    private const BASE_KEY = '__base__';

    /**
     * @var array<FieldConditionMatch>
     */
    private array $matches = [];

    public function __construct(
        private readonly string $fieldName,
        private readonly mixed $condition,
    )
    {}

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * merge the replaced values into the raw config conditions
     *
     * @return mixed
     */
    public function getCondition(): mixed
    {
        if (isset($this->conditionResolveCache['getCondition'])) {
            return $this->conditionResolveCache['getCondition'];
        }

        $template = $this->getRawCondition();
        if (empty($template)) {
            return $this->conditionResolveCache['getCondition'] = null;
        }

        // as soon a base_key value is there it MUST be a single value by design
        if (isset($this->matches[self::BASE_KEY])) {
            return $this->conditionResolveCache['getCondition'] = $this->matches[self::BASE_KEY]->getValue();
        }

        // flatten template (raw Conditions with Placeholder) to remove all placeholder (filter out)
        $cleanFlatTemplate = array_filter(
            $this->flattenArrayKeyPath($template),
            fn ($v) => !is_string($v) || !preg_match(MatchResult::DEFAULT_MATCH_REGEX, $v)
        );

        $resolvedFlat = [];
        // crate a flat array (arrayPath) list with all replaced values
        foreach ($this->matches as $path => $match) {
            $resolvedFlat[$path] = $match->getValue();
        }

        // combine replaced_values flat array and raw_template flat array to fill up static parts.
        return $this->conditionResolveCache['getCondition'] = $this->reconstructFlattenArrayKeyPath($resolvedFlat + $cleanFlatTemplate);
    }

    /**
     * @return mixed
     */
    public function getRawCondition(): mixed
    {
        return $this->condition;
    }

    /**
     * @param string|null $path
     * @param int $matchIndex
     * @param mixed $value
     * @return FieldConditionMatch
     */
    public function addMatch(?string $path, int $matchIndex, mixed $value): FieldConditionMatch
    {
        return $this->matches[$path??self::BASE_KEY] ??= new FieldConditionMatch($value, $matchIndex, $path);
    }

    /**
     * @return array<FieldConditionMatch>
     */
    public function getMatches(): array
    {
        return array_values($this->matches);
    }
}
