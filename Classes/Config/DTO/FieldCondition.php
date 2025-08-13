<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use CPSIT\ShortNr\Traits\ArrayPackTrait;

class FieldCondition implements FieldConditionInterface
{
    use ArrayPackTrait;

    private array $conditionResolveCache = [];

    private const BASE_KEY = '__base__';

    private const MATCH_PREFIX_PLACEHOLDER = '{match-';
    public const DEFAULT_MATCH_REGEX = '/'. self::MATCH_PREFIX_PLACEHOLDER .'(\d+)}/';

    private const MATCH_LIST = [
        '{match-1}' => 1,
        '{match-2}' => 2,
        '{match-3}' => 3,
        '{match-4}' => 4,
        '{match-5}' => 5,
        '{match-6}' => 6,
        '{match-7}' => 7,
        '{match-8}' => 8,
        '{match-9}' => 9,
        '{match-10}' => 10
    ];

    /**
     * @var array<FieldConditionMatch>
     */
    private readonly array $matches;

    public function __construct(
        private readonly string $fieldName,
        private readonly mixed $condition,
    )
    {
        $this->matches = $this->generateMatchList();
    }

    /**
     * return true if there is ANY match placeholder in the Config Conditions
     *
     * @return bool
     */
    public function hasStaticElements(): bool
    {
        die('WIP');
        return false;
    }

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
        // template empty nothing to merge
        if (empty($template)) {
            return $this->conditionResolveCache['getCondition'] = null;
        }

        // no dynamic matches found, must be a pure static FieldCondition, return static parts
        if (empty($this->matches)) {
           return $this->conditionResolveCache['getCondition'] = $template;
        }

        // as soon a base_key value is there it MUST be a single value by design
        if (isset($this->matches[self::BASE_KEY])) {
            return $this->conditionResolveCache['getCondition'] = $this->matches[self::BASE_KEY]->getValue();
        }

        // no nested template transform to array
        if (!is_iterable($template)) {
            $template = [self::BASE_KEY => $template];
        }

        // flatten template (raw Conditions with Placeholder) to remove all placeholder (filter out)
        $cleanFlatTemplate = array_filter(
            $this->flattenArrayKeyPath($template),
            fn ($v) => !is_string($v) || !preg_match(self::DEFAULT_MATCH_REGEX, $v)
        );

        $resolvedFlat = [];
        // crate a flat array (arrayPath) list with all replaced values
        foreach ($this->matches as $path => $match) {
            $resolvedFlat[$path] = $match->getValue();
        }

        // if we only have a BASE_KEY that means its a flat ARRAY
        if (isset($resolvedFlat[self::BASE_KEY])) {
            return $this->conditionResolveCache['getCondition'] = $resolvedFlat[self::BASE_KEY];
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
     * @return FieldConditionMatch[]
     */
    public function getMatches(): array
    {
        return array_values($this->matches);
    }

    /**
     * @return array<FieldConditionMatch>
     */
    public function getProcessedMatches(): array
    {
        $list = [];
        foreach ($this->matches as $match) {
            if ($match->isInitialized()) {
                $list[] = $match;
            }
        }
        return $list;
    }

    /**
     * expect a clean ID => VALUE list (no nested array from REGEX PARSER)
     * return if any matching was processed
     *
     * @param array $matches
     * @return bool
     */
    public function processMatches(array $matches): bool
    {
        $processedAny = false;
        foreach ($this->getMatches() as $conditionMatch) {
            $idx = $conditionMatch->getIdx();
            if (isset($matches[$idx])) {
                $processedAny = true;
                $conditionMatch->setValue($matches[$idx]);
            }
        }

        return $processedAny;
    }

    /**
     * ['dot.path.array.key' => FieldConditionMatch]
     *
     * if no array in condition use the BASE logic to mark it as BASE non Array condition
     *
     * called in constructor
     *
     * @return array<string, FieldConditionMatch>
     */
    private function generateMatchList(): array
    {
        // $this->flattenArrayKeyPath(iterator_to_array($condition)) as $path => $value
        $template = $this->getRawCondition();
        if (empty($template)) {
            return [];
        }

        // single condition
        if (!is_iterable($template)) {
            if ($this->isPlaceholder($template)) {
                return [self::BASE_KEY => new FieldConditionMatch(null, $this->getMatchIndex($template), null)];
            }

            // static single value
            return [];
        }

        $list = [];
        foreach ($this->flattenArrayKeyPath(iterator_to_array($template)) as $path => $value) {
            if ($this->isPlaceholder($value)) {
                $list[$path] = new FieldConditionMatch(null, $this->getMatchIndex($value), $path);
            } // else static value, skip
        }

        return $list;
    }

    /**
     * @param string $value
     * @return bool
     */
    private function isPlaceholder(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return isset(self::MATCH_LIST[$value]) || (str_starts_with($value, self::MATCH_PREFIX_PLACEHOLDER) && str_ends_with($value, '}'));
    }

    /**
     * @param string $placeholder
     * @return int|null
     */
    private function getMatchIndex(string $placeholder): ?int
    {
        // use the predefined map to fast resolve
        if (isset(self::MATCH_LIST[$placeholder])) {
            return self::MATCH_LIST[$placeholder];
        }

        // fallback, process the regex
        if (preg_match(self::DEFAULT_MATCH_REGEX, $placeholder, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }
}
